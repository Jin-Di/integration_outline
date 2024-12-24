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

use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Client;
use OCA\Outline\AppInfo\Application;
use OCP\Files\File;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service to make network requests
 */
class NetworkService {

	private Client $client;

	public function __construct(
		private IConfig $config,
		IClientService $clientService,
		private LoggerInterface $logger,
		private SecretService $secretService,
		private IL10N $l10n
	) {
		$this->client = new Client();
	}


	public function request(string $userId, string $endPoint, array $params = [], string $method = 'POST',
		bool $jsonResponse = true, bool $outlineApiRequest = true) {
		$outlineUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url');
		$apiKey = $this->secretService->getEncryptedUserValue($userId, 'api_key');

		try {
			$url = rtrim($outlineUrl, '/') . '/api/' . $endPoint;
			$options = [
				'headers' => [
					'Authorization' => 'Bearer ' . $apiKey,
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$url .= '?' . http_build_query($params);
				} else {
					$options['json'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}

			$body = $response->getBody()->getContents();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			}

			if ($jsonResponse) {
				return json_decode($body, true);
			}
			return $body;
		} catch (ServerException | ClientException $e) {
			$body = $e->getResponse()->getBody();
			$this->logger->warning('Outline API error : ' . $body, ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Outline API error', ['exception' => $e, 'app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}
}
