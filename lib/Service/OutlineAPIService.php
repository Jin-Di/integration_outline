<?php

/**
 * This file contains code derived from Nextcloud - Zulip
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @author Anupam Kumar <kyteinsky@gmail.com>
 * @author Edward Ly <contact@edward.ly>
 * @author Goh Jin Di <jdgoh334@gmail.com>
 * @copyright Julien Veyssier 2022
 * @copyright Anupam Kumar 2023
 * @copyright Edward Ly 2024
 */


declare(strict_types=1);

namespace OCA\Outline\Service;

use DateTime;
use Exception;
use OC\User\NoUserException;
use OCA\Outline\AppInfo\Application;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\PreConditionNotMetException;
use OCP\Security\ICrypto;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class OutlineAPIService {
        private IClient $client;

        public function __construct(
                private LoggerInterface $logger,
                private IL10N $l10n,
                private IConfig $config,
                private IRootFolder $root,
                private ShareManager $shareManager,
                private IURLGenerator $urlGenerator,
                private ICrypto $crypto,
                private NetworkService $networkService,
                IClientService $clientService
        ) {
                $this->client = $clientService->newClient();
        }

        public function searchMessages(string $userId, string $term, int $offset = 0, int $limit = 10): array {
                $result = $this->request($userId, 'documents.search', [
                        'limit' => $offset + $limit,
                        'query' => $term,
                ]);

                if (isset($result['error'])) {
                        return (array) $result;
                }

                return array_slice($result, $offset, $limit);
        }

        public function request(string $userId, string $endPoint, array $params = [], string $method = 'POST',
                bool $jsonResponse = true, bool $outlineApiRequest = true) {
                return $this->networkService->request($userId, $endPoint, $params, $method, $jsonResponse, $outlineApiRequest);
        }
}
