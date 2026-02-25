<?php

namespace App\Service;

use App\Entity\Course;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CourseInsightsService
{
    private const CACHE_TTL_SECONDS = 21600; // 6h
    private const STACKOVERFLOW_SITE = 'stackoverflow';
    private const RECENT_MONTHS = 12;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(service: 'cache.app')]
        private readonly CacheInterface $cache,
        #[Autowire('%env(default::GITHUB_TOKEN)%')]
        private readonly ?string $githubToken = null,
    ) {
    }

    /**
     * @return array{
     *   keyword: string,
     *   reasons: array<int, array{title: string, value: string, detail: string, link: ?string}>,
     *   chart: array{labels: string[], values: int[], normalized: int[]},
     *   sources: array<string, mixed>
     * }
     */
    public function getInsightsForCourse(Course $course): array
    {
        $keyword = $this->resolveKeyword($course);

        $wikipedia = $this->getWikipediaData($keyword);
        $github = $this->getGithubData($keyword);
        $stackoverflow = $this->getStackOverflowData($keyword);

        $repoCount = (int) ($github['total_repositories'] ?? 0);
        $topRepoStars = (int) ($github['top_repository']['stars'] ?? 0);
        $soTotal = (int) ($stackoverflow['total_questions'] ?? 0);
        $soRecent = (int) ($stackoverflow['recent_questions'] ?? 0);
        $repoLevel = $this->signalLevel($repoCount, 20000, 3000);
        $starsLevel = $this->signalLevel($topRepoStars, 50000, 5000);
        $communityLevel = $this->signalLevel($soTotal, 50000, 8000);
        $recentLevel = $this->signalLevel($soRecent, 3000, 500);

        $chartValues = [$repoCount, $topRepoStars, $soTotal, $soRecent];

        $reasons = [
            [
                'title' => 'What You Will Learn',
                'value' => $wikipedia['status'] === 'ok' ? (string) ($wikipedia['title'] ?? $keyword) : 'Unavailable',
                'detail' => $wikipedia['status'] === 'ok'
                    ? (string) ($wikipedia['extract'] ?? 'No summary available.')
                    : 'Wikipedia summary is currently unavailable for this keyword.',
                'link' => $wikipedia['status'] === 'ok' ? (string) ($wikipedia['url'] ?? '') : null,
            ],
            [
                'title' => 'Real-World Usage',
                'value' => $repoCount > 0 ? $repoLevel : 'Unavailable',
                'detail' => $repoCount > 0
                    ? sprintf('Around %s public projects were found, which suggests practical industry usage.', number_format($repoCount))
                    : 'GitHub repository volume is currently unavailable.',
                'link' => 'https://github.com/search?q=' . rawurlencode($keyword),
            ],
            [
                'title' => 'Trust by Developers',
                'value' => $topRepoStars > 0 ? $starsLevel : 'Unavailable',
                'detail' => $topRepoStars > 0
                    ? sprintf(
                        '%s has about %s stars, a strong trust signal from developers.',
                        (string) ($github['top_repository']['name'] ?? 'A top project'),
                        number_format($topRepoStars),
                    )
                    : 'Top repository stars are currently unavailable.',
                'link' => (string) ($github['top_repository']['url'] ?? ''),
            ],
            [
                'title' => 'Help Availability',
                'value' => $soTotal > 0 ? $communityLevel : 'Unavailable',
                'detail' => $soTotal > 0
                    ? sprintf('There are roughly %s community Q&A threads to support troubleshooting.', number_format($soTotal))
                    : 'StackOverflow total activity is currently unavailable.',
                'link' => 'https://stackoverflow.com/search?q=' . rawurlencode($keyword),
            ],
            [
                'title' => 'Current Momentum',
                'value' => $soRecent > 0 ? $recentLevel : 'Unavailable',
                'detail' => $soRecent > 0
                    ? sprintf('About %s new discussions were posted in the last %d months.', number_format($soRecent), self::RECENT_MONTHS)
                    : 'Recent StackOverflow activity is currently unavailable.',
                'link' => 'https://stackoverflow.com/search?q=' . rawurlencode($keyword),
            ],
        ];

        return [
            'keyword' => $keyword,
            'reasons' => $reasons,
            'chart' => [
                'labels' => ['Project Ecosystem', 'Top Project Trust', 'Learning Support', 'Current Momentum'],
                'values' => $chartValues,
                'normalized' => $this->normalizeForChart($chartValues),
            ],
            'sources' => [
                'wikipedia' => $wikipedia,
                'github' => $github,
                'stackoverflow' => $stackoverflow,
            ],
        ];
    }

    private function resolveKeyword(Course $course): string
    {
        $title = (string) ($course->getTitle() ?? '');
        $description = strip_tags((string) ($course->getDescription() ?? ''));
        $category = (string) ($course->getCategory() ?? '');

        $text = mb_strtolower(trim($title . ' ' . $description));
        $detected = $this->detectKnownTechnology($text);
        if ($detected !== null) {
            return $detected;
        }

        $candidates = $this->extractCandidates($title . ' ' . $description);
        foreach ($candidates as $candidate) {
            if ($this->isValidStackOverflowTag($candidate)) {
                return $candidate;
            }
        }

        $fallback = trim($category) !== '' ? trim($category) : trim($title);
        return $fallback !== '' ? $fallback : 'technology';
    }

    private function detectKnownTechnology(string $text): ?string
    {
        $map = [
            'symfony' => 'symfony',
            'laravel' => 'laravel',
            'react' => 'reactjs',
            'angular' => 'angular',
            'vue' => 'vue.js',
            'node' => 'node.js',
            'nodejs' => 'node.js',
            'typescript' => 'typescript',
            'javascript' => 'javascript',
            'python' => 'python',
            'django' => 'django',
            'flask' => 'flask',
            'java' => 'java',
            'spring' => 'spring-boot',
            'php' => 'php',
            'mysql' => 'mysql',
            'postgresql' => 'postgresql',
            'postgres' => 'postgresql',
            'mongodb' => 'mongodb',
            'docker' => 'docker',
            'kubernetes' => 'kubernetes',
            'machine learning' => 'machine-learning',
            'data science' => 'data-science',
            'deep learning' => 'deep-learning',
            'devops' => 'devops',
        ];

        foreach ($map as $pattern => $keyword) {
            if (str_contains($text, $pattern)) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractCandidates(string $text): array
    {
        $text = mb_strtolower(strip_tags($text));
        $text = preg_replace('/[^a-z0-9\+\#\.\-\s]/u', ' ', $text) ?? $text;
        $words = array_values(array_filter(preg_split('/\s+/', $text) ?: [], static function (string $word): bool {
            if (mb_strlen($word) < 3) {
                return false;
            }

            $stopwords = [
                'course', 'courses', 'learn', 'learning', 'complete', 'guide', 'bootcamp',
                'masterclass', 'introduction', 'intro', 'advanced', 'beginner', 'intermediate',
                'practical', 'ultimate', 'from', 'zero', 'project', 'projects', 'with', 'and',
                'for', 'the', 'using', 'build', 'building', 'development',
            ];

            return !in_array($word, $stopwords, true);
        }));

        if ($words === []) {
            return [];
        }

        $candidates = [];
        foreach ($words as $word) {
            $candidates[] = $word;
        }

        for ($i = 0, $len = count($words) - 1; $i < $len; $i++) {
            $candidates[] = $words[$i] . '-' . $words[$i + 1];
        }

        return array_slice(array_values(array_unique($candidates)), 0, 12);
    }

    private function isValidStackOverflowTag(string $candidate): bool
    {
        $cacheKey = 'course_insights.tag_valid.' . md5($candidate);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($candidate): bool {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            $url = sprintf(
                'https://api.stackexchange.com/2.3/tags/%s/info?site=%s',
                rawurlencode($candidate),
                self::STACKOVERFLOW_SITE,
            );

            $data = $this->requestJson($url);
            if (!is_array($data) || !isset($data['items'][0]['count'])) {
                return false;
            }

            return (int) $data['items'][0]['count'] > 100;
        });
    }

    /**
     * @return array{status: string, title?: string, extract?: string, url?: string}
     */
    private function getWikipediaData(string $keyword): array
    {
        $cacheKey = 'course_insights.wikipedia.' . md5($keyword);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($keyword): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            $summaryUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($keyword);
            $summary = $this->requestJson($summaryUrl);

            if (is_array($summary) && isset($summary['extract']) && trim((string) $summary['extract']) !== '') {
                return [
                    'status' => 'ok',
                    'title' => (string) ($summary['title'] ?? $keyword),
                    'extract' => trim((string) $summary['extract']),
                    'url' => (string) ($summary['content_urls']['desktop']['page'] ?? ''),
                ];
            }

            $searchUrl = 'https://en.wikipedia.org/w/api.php?action=query&list=search&format=json&srlimit=1&utf8=1&srsearch=' . rawurlencode($keyword);
            $search = $this->requestJson($searchUrl);
            $firstTitle = (string) ($search['query']['search'][0]['title'] ?? '');
            if ($firstTitle === '') {
                return ['status' => 'unavailable'];
            }

            $fallbackSummary = $this->requestJson('https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($firstTitle));
            if (!is_array($fallbackSummary) || trim((string) ($fallbackSummary['extract'] ?? '')) === '') {
                return ['status' => 'unavailable'];
            }

            return [
                'status' => 'ok',
                'title' => (string) ($fallbackSummary['title'] ?? $firstTitle),
                'extract' => trim((string) $fallbackSummary['extract']),
                'url' => (string) ($fallbackSummary['content_urls']['desktop']['page'] ?? ''),
            ];
        });
    }

    /**
     * @return array{
     *   status: string,
     *   total_repositories?: int,
     *   top_repository?: array{name: string, stars: int, url: string, description: string}
     * }
     */
    private function getGithubData(string $keyword): array
    {
        $cacheKey = 'course_insights.github.' . md5($keyword);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($keyword): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            $url = 'https://api.github.com/search/repositories?q=' . rawurlencode($keyword . ' in:name,description') . '&sort=stars&order=desc&per_page=5';
            $headers = ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'SkillORA-Course-Insights'];
            $token = trim((string) $this->githubToken);
            if ($token !== '') {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            $data = $this->requestJson($url, $headers);
            if (!is_array($data)) {
                return ['status' => 'unavailable'];
            }

            $top = $data['items'][0] ?? null;

            return [
                'status' => 'ok',
                'total_repositories' => (int) ($data['total_count'] ?? 0),
                'top_repository' => [
                    'name' => (string) ($top['full_name'] ?? ''),
                    'stars' => (int) ($top['stargazers_count'] ?? 0),
                    'url' => (string) ($top['html_url'] ?? ''),
                    'description' => trim((string) ($top['description'] ?? '')),
                ],
            ];
        });
    }

    /**
     * @return array{status: string, total_questions?: int, recent_questions?: int}
     */
    private function getStackOverflowData(string $keyword): array
    {
        $cacheKey = 'course_insights.stackoverflow.' . md5($keyword);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($keyword): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            $base = 'https://api.stackexchange.com/2.3/search/advanced?site=' . self::STACKOVERFLOW_SITE . '&pagesize=1&filter=total';
            $totalData = $this->requestJson($base . '&order=desc&sort=relevance&title=' . rawurlencode($keyword));

            $fromDate = (new \DateTimeImmutable('-' . self::RECENT_MONTHS . ' months'))->getTimestamp();
            $recentData = $this->requestJson($base . '&order=desc&sort=activity&fromdate=' . $fromDate . '&title=' . rawurlencode($keyword));

            if (!is_array($totalData) && !is_array($recentData)) {
                return ['status' => 'unavailable'];
            }

            return [
                'status' => 'ok',
                'total_questions' => (int) ($totalData['total'] ?? 0),
                'recent_questions' => (int) ($recentData['total'] ?? 0),
            ];
        });
    }

    /**
     * @param list<int> $values
     * @return list<int>
     */
    private function normalizeForChart(array $values): array
    {
        $logs = array_map(static fn (int $v): float => log10(max(0, $v) + 1), $values);
        $max = max($logs ?: [1.0]);
        if ($max <= 0) {
            return array_fill(0, count($values), 0);
        }

        return array_map(static fn (float $v): int => (int) round(($v / $max) * 100), $logs);
    }

    private function signalLevel(int $value, int $highThreshold, int $mediumThreshold): string
    {
        if ($value >= $highThreshold) {
            return 'Very Strong';
        }

        if ($value >= $mediumThreshold) {
            return 'Strong';
        }

        if ($value > 0) {
            return 'Growing';
        }

        return 'Unavailable';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestJson(string $url, array $headers = []): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 15,
                'headers' => $headers,
            ]);

            if ($response->getStatusCode() >= 400) {
                return null;
            }

            $data = $response->toArray(false);
            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
