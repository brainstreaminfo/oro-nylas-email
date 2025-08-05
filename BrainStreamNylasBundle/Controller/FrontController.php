<?php

/**
 * Front Controller for Nylas integration.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Controller
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Controller;

use BrainStream\Bundle\NylasBundle\Service\ConfigService;
use BrainStream\Bundle\NylasBundle\Service\NylasApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;


/**
 * Front Controller for Nylas integration.
 *
 * Handles Nylas authentication, folder management, and account operations.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Controller
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class FrontController extends AbstractController
{
    private ConfigService $configService;

    private RequestStack $requestStack;

    private LoggerInterface $logger;

    private string $nylasClientId;

    private string $nylasClientSecret;

    private mixed $user;

    private ?int $userId = null;

    private NylasApiService $nylasApiService;

    private string $baseUrl;

    private mixed $client;

    private EntityManagerInterface $entityManager;

    /**
     * Constructor for FrontController.
     *
     * @param RequestStack           $requestStack    The request stack
     * @param ConfigService          $configService   The config service
     * @param LoggerInterface        $logger          The logger
     * @param NylasApiService        $nylasApiService The Nylas API service
     * @param EntityManagerInterface $entityManager   The entity manager
     */
    public function __construct(
        RequestStack $requestStack,
        ConfigService $configService,
        LoggerInterface $logger,
        NylasApiService $nylasApiService,
        EntityManagerInterface $entityManager
    ) {
        $this->requestStack = $requestStack;
        $this->configService = $configService;
        $this->logger = $logger;
        $this->nylasClientId = $this->configService->getClientId();
        $this->nylasClientSecret = $this->configService->getClientSecret();
        $this->baseUrl = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
        //$this->baseUrl = 'http://127.0.0.1:8000';
        $this->nylasApiService = $nylasApiService;
        $this->client = HttpClient::create(
            [
                'timeout' => 60,
                'max_duration' => 60,
                'verify_peer' => false,
                'verify_host' => false,
                //'http_version' => '1.1'
            ]
        );
        $this->entityManager = $entityManager;
    }

    /**
     * Nylas authentication.
     *
     * @return Response
     */
    #[Route('/nylas/auth', name: 'nylas_auth')]
    public function authAction(): Response
    {
        $redirectUri = $this->baseUrl . $this->generateUrl('nylas_auth_callback');
        //call nylas auth url
        $authUrl = $this->configService->getApiUrl() . "/v3/connect/auth?" . http_build_query(
            [
                'client_id' => $this->nylasClientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'access_type' => 'online',
                //'provider' => 'google',//microsoft
            ]
        );
        //ref:adbrain authurl echo $authUrl

        return $this->redirect($authUrl);
    }

    /**
     * Nylas authentication callback.
     *
     * @param Request $request The request
     *
     * @return Response
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[Route(path: "/nylas/auth/callback", name: "nylas_auth_callback")]
    public function authCallbackAction(Request $request): Response
    {
        $this->userId = $this->getUser()->getId();
        $code = $request->query->get('code');
        if (!$code) {
            $this->addFlash('error', 'No authorization code returned from Nylas');
            return $this->redirectToRoute('nylas_email_folder_list');
        }
        $redirectUri = $this->baseUrl . $this->generateUrl('nylas_auth_callback');

        try {
            // get nylas token
            $response = $this->client->request(
                'POST',
                $this->configService->getApiUrl() . '/v3/connect/token',
                [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'client_id' => $this->nylasClientId,
                        'client_secret' => $this->nylasClientSecret,
                        'grant_type' => 'authorization_code',
                        'code' => $code,
                        'redirect_uri' => $redirectUri,
                    ],
                ]
            );
            $tokenData = $response->toArray();
            // Save nylas token and create/update email origin
            $emailOrigin = $this->nylasApiService->saveNylasToken($this->userId, $tokenData);

            $this->entityManager->flush();
            //ref:adbrain removed clear($this->entityManager->clear()) as its causing issues

            $this->addFlash('success', 'Account connected successfully');
            return $this->redirectToRoute(
                'nylas_email_folder_list',
                [
                    'id' => $emailOrigin->getId(),
                    '_t' => time(),
                    'new_account' => 1
                ],
                Response::HTTP_SEE_OTHER
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to exchange token', ['message' => $e->getMessage()]);
            $this->addFlash('error', 'Authentication failed: ' . $e->getMessage());
            return $this->redirectToRoute('nylas_email_folder_list');
        }
    }

    /**
     * Get folder list action, also saves folder list.
     *
     * @param Request  $request The request
     * @param int|null $id      The origin ID
     *
     * @return Response
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[Route(path: "/nylas/folderList/{id}", name: "nylas_email_folder_list", defaults: ["id" => null])]
    public function folderListAction(Request $request, ?int $id = null): Response
    {
        $this->userId = $this->getUser()->getId();
        $folders = [];
        $emailOrigin = [];

        try {
            //ref:adbrain removed clear as its causing issues
            // Fetch all email origins for the user
            $response = $this->nylasApiService->getAccountInfo($this->userId);
            $emailOrigins = $response['origins'] ?? [];

            // If an ID is provided, use it and check for active status; otherwise, find the default origin
            if ($id) {
                foreach ($emailOrigins as $origin) {
                    if ($origin['id'] == $id && $origin['activeStatus'] == true) {
                        $emailOrigin = $origin;
                        break;
                    } elseif ($origin['activeStatus'] == true) {
                        $emailOrigin = $origin;
                    }
                }
            }

            // If no ID is provided or the ID is invalid, fall back to the default origin
            if ((!$id) && (!$emailOrigin)) {
                foreach ($emailOrigins as $origin) {
                    if ($origin['isDefault'] == true) {
                        $emailOrigin = $origin;
                        break;
                    } elseif (!$emailOrigin && $origin['activeStatus'] == true && $origin['isDefault'] == false) {
                        $emailOrigin = $origin;
                    }
                }
            }

            // If no origin is found, render an empty page
            if (!$emailOrigin) {
                return $this->render(
                    '@BrainStreamNylas/templates/folderList.html.twig',
                    [
                        'emailFolders' => [],
                        'email' => '',
                        'isMultipleEmail' => $response['isMultipleEmail'] ?? false,
                        'emailOrigins' => $emailOrigins,
                        'selectedEmailOriginId' => null,
                    ]
                );
            }
            // Fetch and create folders for the selected email origin
            if (!empty($emailOrigin['accountId'])) {
                $folders = $this->getFolderList($emailOrigin);
                $this->logger->info(
                    '=================Folders loaded for origin id = ' . $emailOrigin['id'] . '==========>',
                    [
                        'folders' => $folders,
                    ]
                );
            } else {
                $this->addFlash('error', 'Invalid email origin: accountId is missing.');
            }

            // Save folder preferences if the form is submitted
            if ($request->isMethod('POST')) {
                $postData = $request->request->all();
                $selectedFolders = $postData['folders'] ?? [];
                $this->logger->info(
                    'Selected folders from form',
                    [
                        'selected_folders' => $selectedFolders,
                        'origin_id' => $emailOrigin['id'],
                        'timestamp' => '2025-06-04 19:07:00 IST'
                    ]
                );

                $this->saveFolderList($selectedFolders, $emailOrigin['id']);
                $this->addFlash('success', 'Folder sync preferences updated successfully.');
                return $this->redirectToRoute(
                    'nylas_email_folder_list',
                    [
                        'id' => $emailOrigin['id'],
                        '_t' => time() // Add current timestamp
                    ],
                    Response::HTTP_SEE_OTHER
                );
            }

            return $this->render(
                '@BrainStreamNylas/templates/folderList.html.twig',
                [
                    'emailFolders' => $folders['folders'] ?? [],
                    'email' => $folders['mailboxName'] ?? '',
                    'isMultipleEmail' => $response['isMultipleEmail'] ?? false,
                    'emailOrigins' => $emailOrigins,
                    'selectedEmailOriginId' => $emailOrigin['id'] ?? null,
                ]
            );
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to fetch folder list: ' . $e->getMessage());
            return $this->render(
                '@BrainStreamNylas/templates/folderList.html.twig',
                [
                    'emailFolders' => [],
                    'email' => '',
                    'isMultipleEmail' => false,
                    'emailOrigins' => [],
                    'selectedEmailOriginId' => null,
                ]
            );
        }
    }

    /**
     * Save folder list.
     *
     * @param array $selectedFolders The selected folders
     * @param int   $originId        The origin ID
     *
     * @return void
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function saveFolderList(array $selectedFolders, int $originId): void
    {
        //call save folder list api
        $result = $this->nylasApiService->saveEmailFolders($selectedFolders, $originId);
        if (!$result) {
            throw new \RuntimeException('Failed to save sync preferences: ');
        }
    }

    /**
     * Get folder list.
     *
     * @param array $emailOrigin The email origin
     *
     * @return array
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function getFolderList(array $emailOrigin): array
    {
        $params = [
            'userId' => $this->getUser()->getId(),
            'accountId' => $emailOrigin['accountId'] ?? '',
            'provider' => $emailOrigin['provider'] ?? '',
            'tokenType' => $emailOrigin['tokenType'] ?? 'Bearer',
            'email' => $emailOrigin['email'] ?? '',
        ];
        $folders = $this->nylasApiService->getEmailFolders($params);
        return $folders;
    }

    /**
     * Set default account action.
     *
     * @param Request $request The request
     * @param int     $id      The origin ID
     *
     * @return RedirectResponse
     */
    #[Route(
        path: "/nylas/setDefaultAccount/{id}",
        name: "nylas_set_default_account",
        methods: ["POST"],
        requirements: ['id' => '\d+']
    )]
    public function setDefaultAccountAction(Request $request, int $id): RedirectResponse
    {
        try {
            $result = $this->nylasApiService->setDefaultAccount($id);
            if ($result == 0) {
                $this->addFlash('error', 'Deactived account can not be set default.');
                return $this->redirectToRoute('nylas_email_folder_list', ['id' => $id], Response::HTTP_SEE_OTHER);
            }
            $this->addFlash('success', 'Account set as default successfully.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to set default account: ' . $e->getMessage());
        }
        return $this->redirectToRoute('nylas_email_folder_list', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    /**
     * Set account status action.
     *
     * @param Request $request The request
     * @param int     $id      The origin ID
     * @param string  $status  The status
     *
     * @return RedirectResponse
     */
    #[Route(
        path: "/nylas/setAccountStatus/{id}/{status}",
        name: "nylas_set_account_status",
        methods: ["POST"],
        requirements: ['id' => '\d+']
    )]
    public function setAccountStatusAction(Request $request, int $id, string $status): JsonResponse
    {
        try {
            $this->nylasApiService->setAccountStatus($id, $status);

            return new JsonResponse([
                'success' => true,
                'message' => 'Account status updated successfully.',
                'id' => $id,
                'status' => $status
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to set status: ' . $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
