<?php

namespace App\Services;

use App\Exceptions\YandexMapsParsingException;
use Closure;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Throwable;

class YandexMapsOrganizationParser
{
    private const PAGE_SIZE = 50;

    /** @var list<int> */
    private const RETRY_DELAYS_MS = [1000, 3000];

    private const RETRYABLE_STATUSES = [408, 425, 429, 500, 502, 503, 504];

    private CookieJar $cookies;

    /**
     * @param  null|Closure(int): void  $onProgress
     * @return array{rating: float|null, rating_count: int, review_count: int, reviews: list<array{external_id: string, author: string, reviewed_at: string, text: string|null, rating: int}>}
     */
    public function parse(string $url, ?Closure $onProgress = null): array
    {
        $this->cookies = new CookieJar;
        $reviewsUrl = $this->resolveReviewsUrl($url);
        $firstPage = $this->fetch($this->pageUrl($reviewsUrl, 1));
        $ratingData = $this->extractObject($firstPage->body(), 'ratingData');

        if (! array_key_exists('ratingCount', $ratingData)
            || ! array_key_exists('reviewCount', $ratingData)
            || ! array_key_exists('ratingValue', $ratingData)) {
            throw new YandexMapsParsingException('Яндекс изменил формат данных рейтинга и счётчиков.');
        }

        $ratingCount = $this->normalizeCount($ratingData['ratingCount'], 'количества оценок');
        $reviewCount = $this->normalizeCount($ratingData['reviewCount'], 'количества отзывов');
        $rating = $ratingCount === 0 ? null : $this->normalizeRating($ratingData['ratingValue']);
        $expectedPages = max(1, (int) ceil($reviewCount / self::PAGE_SIZE));
        $maxPages = max(1, (int) config('services.yandex_maps.max_pages', 100));
        $reviews = [];
        $sourceExhausted = false;

        for ($page = 1; $page <= $expectedPages; $page++) {
            if ($page > $maxPages) {
                throw new YandexMapsParsingException(
                    "Не удалось подтвердить полный сбор отзывов за {$maxPages} страниц. Увеличьте лимит парсера.",
                );
            }

            if ($page > 1) {
                usleep(max(0, (int) config('services.yandex_maps.page_delay_ms')) * 1000);
            }

            $html = $page === 1 ? $firstPage->body() : $this->fetch($this->pageUrl($reviewsUrl, $page))->body();
            $pageReviews = $this->extractReviews($html);

            if ($pageReviews === []) {
                if ($page === 1 && $reviewCount > 0) {
                    throw new YandexMapsParsingException('В карточке указаны отзывы, но получить их не удалось.');
                }

                $sourceExhausted = true;

                break;
            }

            $newReviews = 0;

            foreach ($pageReviews as $review) {
                if (! array_key_exists($review['external_id'], $reviews)) {
                    $newReviews++;
                }

                $reviews[$review['external_id']] = $review;
            }

            if ($newReviews === 0) {
                throw new YandexMapsParsingException(
                    'Яндекс повторил уже полученную страницу отзывов. Полноту сбора подтвердить не удалось.',
                );
            }

            if ($onProgress !== null) {
                $onProgress(min(90, 10 + $page * 5));
            }

            if (count($reviews) >= $reviewCount) {
                break;
            }
        }

        if (! $sourceExhausted && count($reviews) < $reviewCount) {
            throw new YandexMapsParsingException(
                'Количество полученных отзывов не совпало со счётчиком карточки. Полноту сбора подтвердить не удалось.',
            );
        }

        return [
            'rating' => $rating,
            'rating_count' => $ratingCount,
            'review_count' => $reviewCount,
            'reviews' => array_values($reviews),
        ];
    }

    private function resolveReviewsUrl(string $url): string
    {
        $this->assertAllowedUrl($url);
        $parts = parse_url($url);

        if (preg_match('~^/maps/org/(?:[^/]+/)?[1-9][0-9]*~', $parts['path'] ?? '', $match)) {
            return sprintf('%s://%s%s/reviews/', $parts['scheme'], $parts['host'], rtrim($match[0], '/'));
        }

        $effectiveUrl = $url;
        $this->fetch($url, function (string $resolvedUrl) use (&$effectiveUrl): void {
            $effectiveUrl = $resolvedUrl;
        });
        $parts = parse_url($effectiveUrl);

        if (! preg_match('~^/maps/org/(?:[^/]+/)?[1-9][0-9]*~', $parts['path'] ?? '', $match)) {
            throw new YandexMapsParsingException('Короткая ссылка не привела к карточке организации.');
        }

        return sprintf('%s://%s%s/reviews/', $parts['scheme'], $parts['host'], rtrim($match[0], '/'));
    }

    private function pageUrl(string $reviewsUrl, int $page): string
    {
        return $reviewsUrl.'?'.http_build_query(['page' => $page]);
    }

    /** @param null|Closure(string): void $onEffectiveUrl */
    private function fetch(string $url, ?Closure $onEffectiveUrl = null): Response
    {
        try {
            $response = Http::accept('text/html,application/xhtml+xml')
                ->withHeaders([
                    'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.7',
                    'User-Agent' => config('services.yandex_maps.user_agent'),
                ])
                ->connectTimeout((int) config('services.yandex_maps.connect_timeout'))
                ->timeout((int) config('services.yandex_maps.timeout'))
                ->retry(
                    self::RETRY_DELAYS_MS,
                    when: static function (Throwable $exception): bool {
                        if ($exception instanceof ConnectionException) {
                            return true;
                        }

                        return $exception instanceof RequestException
                            && in_array($exception->response->status(), self::RETRYABLE_STATUSES, true);
                    },
                    throw: false,
                )
                ->withOptions([
                    'cookies' => $this->cookies,
                    'on_stats' => function ($stats) use ($onEffectiveUrl): void {
                        $effectiveUrl = (string) $stats->getEffectiveUri();
                        $this->assertAllowedUrl($effectiveUrl);
                        if ($onEffectiveUrl !== null) {
                            $onEffectiveUrl($effectiveUrl);
                        }
                    },
                    'allow_redirects' => [
                        'max' => 5,
                        'strict' => true,
                        'referer' => false,
                        'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri): void {
                            $this->assertAllowedUrl((string) $uri);
                        },
                    ],
                ])
                ->get($url);
        } catch (YandexMapsParsingException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            throw new YandexMapsParsingException(
                'Яндекс Карты не ответили вовремя. Повторите обновление позже.',
                previous: $exception,
            );
        } catch (Throwable $exception) {
            report($exception);

            throw new YandexMapsParsingException('Не удалось загрузить карточку организации в Яндекс Картах.');
        }

        if (in_array($response->status(), [403, 429], true)) {
            throw new YandexMapsParsingException('Яндекс Карты временно ограничили доступ к карточке. Попробуйте позже.');
        }

        if (! $response->successful()) {
            throw new YandexMapsParsingException('Карточка организации в Яндекс Картах недоступна.');
        }

        return $response;
    }

    /** @return list<array{external_id: string, author: string, reviewed_at: string, text: string|null, rating: int}> */
    private function extractReviews(string $html): array
    {
        $sourceReviews = $this->extractObject($html, 'reviewResults')['reviews'] ?? null;

        if (! is_array($sourceReviews)) {
            throw new YandexMapsParsingException('Яндекс изменил формат списка отзывов.');
        }

        $reviews = [];

        foreach ($sourceReviews as $sourceReview) {
            if (! is_array($sourceReview)
                || ! is_string($sourceReview['reviewId'] ?? null)
                || ! is_string($sourceReview['updatedTime'] ?? null)
                || ! is_numeric($sourceReview['rating'] ?? null)) {
                throw new YandexMapsParsingException('Яндекс вернул отзыв в неизвестном формате.');
            }

            $rating = (int) $sourceReview['rating'];

            if ((float) $sourceReview['rating'] === 0.0) {
                continue;
            }

            if ($rating < 1 || $rating > 5) {
                throw new YandexMapsParsingException('Яндекс вернул оценку отзыва вне допустимого диапазона.');
            }

            $reviews[] = [
                'external_id' => $sourceReview['reviewId'],
                'author' => is_string($sourceReview['author']['name'] ?? null)
                    && $sourceReview['author']['name'] !== ''
                        ? $sourceReview['author']['name']
                        : 'Анонимный пользователь',
                'reviewed_at' => $sourceReview['updatedTime'],
                'text' => is_string($sourceReview['text'] ?? null) && $sourceReview['text'] !== '' ? $sourceReview['text'] : null,
                'rating' => $rating,
            ];
        }

        return $reviews;
    }

    /** @return array<string, mixed> */
    private function extractObject(string $html, string $property): array
    {
        $marker = '"'.$property.'":';
        $offset = 0;

        while (($markerPosition = strpos($html, $marker, $offset)) !== false) {
            $start = strpos($html, '{', $markerPosition + strlen($marker));

            if ($start === false) {
                break;
            }

            $decoded = json_decode($this->balancedJsonObject($html, $start), true);

            if (is_array($decoded)) {
                return $decoded;
            }

            $offset = $start + 1;
        }

        throw new YandexMapsParsingException("Не удалось найти {$property} в карточке. Возможно, Яндекс изменил формат страницы.");
    }

    private function balancedJsonObject(string $html, int $start): string
    {
        $depth = 0;
        $insideString = false;
        $escaped = false;

        for ($position = $start, $length = strlen($html); $position < $length; $position++) {
            $character = $html[$position];

            if ($insideString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $insideString = false;
                }

                continue;
            }

            if ($character === '"') {
                $insideString = true;
            } elseif ($character === '{') {
                $depth++;
            } elseif ($character === '}' && --$depth === 0) {
                return substr($html, $start, $position - $start + 1);
            }
        }

        throw new YandexMapsParsingException('Встроенные данные карточки Яндекса повреждены или имеют неизвестный формат.');
    }

    private function normalizeRating(mixed $value): float
    {
        if (! is_numeric($value)) {
            throw new YandexMapsParsingException('Яндекс Карты вернули рейтинг в неизвестном формате.');
        }

        $rating = round((float) $value, 1);

        if ($rating < 1 || $rating > 5) {
            throw new YandexMapsParsingException('Яндекс Карты вернули рейтинг вне допустимого диапазона.');
        }

        return $rating;
    }

    private function normalizeCount(mixed $value, string $label): int
    {
        if (! is_numeric($value) || (int) $value < 0) {
            throw new YandexMapsParsingException("Яндекс вернул некорректное значение {$label}.");
        }

        return (int) $value;
    }

    private function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)
            || ! in_array($host, config('services.yandex_maps.allowed_hosts'), true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new YandexMapsParsingException('Яндекс перенаправил запрос на неподдерживаемый адрес.');
        }
    }
}
