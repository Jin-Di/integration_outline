<?php

declare(strict_types=1);

namespace OCA\Outline\Search;

use OCA\Outline\AppInfo\Application;
use OCA\Outline\Service\SecretService;
use OCA\Outline\Service\OutlineAPIService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;
use Psr\Log\LoggerInterface;

class OutlineSearchProvider implements IProvider {

        public function __construct(
                private LoggerInterface $logger,
                private IAppManager $appManager,
                private IL10N $l10n,
                private IConfig $config,
                private IURLGenerator $urlGenerator,
                private IDateTimeFormatter $dateTimeFormatter,
                private IDateTimeZone $dateTimeZone,
                private SecretService $secretService,
                private OutlineAPIService $apiService
        ) {
        }

        public function getId(): string {
                return 'outline-search-messages';
        }

        public function getName(): string {
                return $this->l10n->t('Outline Knowledge Base');
        }

        public function getOrder(string $route, array $routeParameters): int {
                return 20; // Adjust priority as needed
        }

        public function search(IUser $user, ISearchQuery $query): SearchResult {
                if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
                        return SearchResult::complete($this->getName(), []);
                }

                $limit = $query->getLimit();
                $term = $query->getTerm();
				$offset = ($query->getCursor() ?? 0);

                $url = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'url');
                $apiKey = $this->secretService->getEncryptedUserValue($user->getUID(), 'api_key');

                if ($url === '' || $apiKey === '' ) {
                        return SearchResult::paginated($this->getName(), [], 0);
                }

                // Call Outline API
                $searchResult = $this->apiService->searchMessages($user->getUID(), $term, $offset, $limit);
                if (isset($searchResult['error'])) {
                        return SearchResult::paginated($this->getName(), [], 0);
                }

                $dataEntries = $searchResult['data'] ?? [];
                $formattedResults = array_map(function (array $entry) use ($url): SearchResultEntry {
                        $finalThumbnailUrl = '';
                        $title = $entry['document']['title'] ?? 'Untitled';
                        $context = $entry['context'] ?? '';
                        $link = $this->getLinkToOutline($entry, $url);
                        return new SearchResultEntry(
                                $finalThumbnailUrl,
                                $title,
                                strip_tags($context),
                                $link,
                                $finalThumbnailUrl,
                                true
                        );
                }, $dataEntries);

                return SearchResult::paginated(
                        $this->getName(),
                        $formattedResults,
                        $offset + $limit
                );
        }

        /**
         * @param array $entry
         * @param string $url
         * @return string
         */
        protected function getLinkToOutline(array $entry, string $url): string {
                return rtrim($url, '/') . ($entry['document']['url'] ?? '#');
        }
}
