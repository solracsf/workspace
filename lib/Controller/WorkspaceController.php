<?php
namespace OCA\Workspace\Controller;

use OCA\Workspace\Service\UserService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Authentication\LoginCredentials\ICredentials;
use OCP\Authentication\LoginCredentials\IStore;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\AppFramework\Http;

class WorkspaceController extends Controller {
    
    /** @var IStore */
    private $IStore;

    /** @var IClient */
    private $httpClient;

    /** @var ICredentials */
    private $login;

    /** @var IGroupManager */
    private $groupManager;

    /** @var IURLGenerator */
    private $urlGenerator;

    /** @var UserService */
    private $userService;

    public function __construct(
        $AppName,
        IClientService $clientService,
        IGroupManager $groupManager,
        IRequest $request,
        IURLGenerator $urlGenerator,
	    UserService $userService,
        IStore $IStore
    )
    {
        parent::__construct($AppName, $request);

	    $this->groupManager = $groupManager;
        $this->IStore = $IStore;
        $this->urlGenerator = $urlGenerator;
        $this->userService = $userService;

	    $this->login = $this->IStore->getLoginCredentials();

        $this->httpClient = $clientService->newClient();
    }

    /**
     *
     * Returns a list of all the workspaces that the connected user
     * may use.
     *
     * @NoAdminRequired
     * 
     */
    public function getUserWorkspaces() {
        
	// Gets all groupfolders
        $response = $this->httpClient->get(
            $this->urlGenerator->getBaseUrl() . '/apps/groupfolders/folders?format=json',
            [
                'auth' => [
                    $this->login->getUID(),
                    $this->login->getPassword()
                ],
                'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'OCS-APIRequest' => 'true',
                        'Accept' => 'application/json',
                ],
		'verify' => false,
            ]
        );

	// TODO Check response first
	// TODO Filter to show only workspaces, not regular groupfolders
	
	$spaces = json_decode($response->getBody(), true);
	$spaces = $spaces['ocs']['data'];
	
	// We only want to return those workspaces for which the connected user is a manager
	if (!$this->userService->isUserGeneralAdmin()) {
		$filteredSpaces = array_filter($spaces, function($space) {
			return $this->userService->isSpaceManagerOfSpace($space['mount_point']);
		});
        	$spaces = $filteredSpaces;
	}

	// Adds workspace users
	// TODO We still need to get the workspace color here
	$spacesWithUsers = array_map(function($space) {
		$space['admins'] = $this->groupManager->get('GE-' . $space['mount_point'])->getUsers();
		$space['users'] = $this->groupManager->get('U-' . $space['mount_point'])->getUsers();
		return $space;
		
	},$spaces);

        return new JSONResponse($spacesWithUsers);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * TODO: Manage errors.
     * @param int $folderId
     * @param string $newSpaceName
     * @return JSONResponse
     */
    public function rename($folderId, $newSpaceName) {

        // Todo : create the groupfolderService with this method.
        // $oldSpaceName = $this->groupfolder->getMountPoint($folderId),
        // $oldSpaceName = $this->groupfolder->getGroups($folderId),
     
        $responseGetGroupfolder = $this->httpClient->get(
            $this->urlGenerator->getBaseUrl() . '/apps/groupfolders/folders/' . $folderId,
            [
                'auth' => [
                    $this->login->getUID(),
                    $this->login->getPassword()
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'OCS-APIRequest' => 'true',
                    'Accept' => 'application/json',
                    'verify' => 'false',
                ]
            ]);

        $groupfolder = json_decode($responseGetGroupfolder->getBody(), true);

        $oldSpaceName = $groupfolder['ocs']['data']['mount_point'];
        $groups = array_keys($groupfolder['ocs']['data']['groups']);
     
        $responseGroupfolder = $this->httpClient->post(
            $this->urlGenerator->getBaseUrl() . '/apps/groupfolders/folders/'. $folderId .'/mountpoint',
            [
                'auth' => [
                    $this->login->getUID(),
                    $this->login->getPassword()
                ],
                'body' => [
                    'mountpoint' => $newSpaceName
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'OCS-APIRequest' => 'true',
                    'Accept' => 'application/json',
                    'verify' => 'false',
                ]
            ]);

        $responseRename = json_decode($responseGroupfolder->getBody(), true);

        if( $responseRename['ocs']['meta']['statuscode'] === 100 ) {
            $response = [
                "statuscode" => Http::STATUS_NO_CONTENT,
                "space" => $newSpaceName
            ];
            
            // Todo : create the groupService with this method.
            // newGroupGE = this->groupService->renameGroupSpace($oldSpaceName, 'GE-'.$newSpace)
            // newGroupU = this->groupService->renameGroupSpace($oldSpaceName, 'U-'.$newSpace)

            // [X] Get list users' interfaces in one group
            $groupGE = $this->groupManager->get('GE-' . $oldSpaceName);
            $groupU = $this->groupManager->get('U-' . $oldSpaceName);

            $IUsersGE = $groupGE->getUsers();
            $IUsersU = $groupU->getUsers();
            
            // [X] Create groups which are the same name that the space renamed
            $newGroupGE = $this->groupManager->createGroup('GE-' . $newSpaceName);
            $newGroupU = $this->groupManager->createGroup('U-' . $newSpaceName);

            // [X] Affect users in new groups with respect the order (GE/U)
            foreach ($IUsersGE as $IUserGE) {
                $newGroupGE->addUser($IUserGE);
            }

            foreach ($IUsersU as $IUserU) {
                $newGroupU->addUser($IUsersU);
            }

            // [X] Attach new groups in space renamed
            $respAttachGroupGE = $this->httpClient->post(
                $this->urlGenerator->getBaseUrl() . '/apps/groupfolders/folders/' . $folderId . '/groups',
                [
                    'auth' => [
                        $this->login->getUID(),
                        $this->login->getPassword()
                    ],
                    'body' => [
                        'group' => $newGroupGE->getGID()
                    ],
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'OCS-APIRequest' => 'true',
                        'Accept' => 'application/json',
                    ]
                ]
            );
            
            if ($respAttachGroupGE->getStatusCode() === 200) {
                $response['groups'][] = $newGroupGE->getGID();
            }

            $respAttachGroupU = $this->httpClient->post(
                $this->urlGenerator->getBaseUrl() . '/apps/groupfolders/folders/' . $folderId . '/groups',
                [
                    'auth' => [
                        $this->login->getUID(),
                        $this->login->getPassword()
                    ],
                    'body' => [
                        'group' => $newGroupU->getGID()
                    ],
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'OCS-APIRequest' => 'true',
                        'Accept' => 'application/json',
                    ]
                ]
            );

            if ($respAttachGroupU->getStatusCode() === 200) {
                $response['groups'][] = $newGroupU->getGID();
            }
        
            // [X] Delete old groups
            $groupGE->delete();
            $groupU->delete();
        }

        return new JSONResponse($response);
    }

    /**
     *
     * TODO This is a single API call. It should probably be moved to the frontend
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * 
     * @var string $folderId
     * @var string $gid
     */
    public function addGroupAdvancedPermissions($folderId, $gid){

        $dataResponse = $this->httpClient->post(
            $this->urlGenerator->getBaseUrl() . '/apps/groupfolders/folders/'. $folderId .'/manageACL',
            [
                'auth' => [
                    $this->login->getUID(),
                    $this->login->getPassword()
                ],
                'body' => [
                        'mappingType' => 'group',
                        'mappingId' => $gid,
                        'manageAcl' => true
                ],
                'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'OCS-APIRequest' => 'true',
                        'Accept' => 'application/json',
                ]
            ]
        );

        $response = json_decode($dataResponse->getBody(), true);

        return new JSONResponse($response);
    }
}
