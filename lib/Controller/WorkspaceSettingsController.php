<?php

use OCP\AppFramework\Controller;

class WorkspaceSettingsController extends Controller {
    /**
     * Save Settings
     * @PasswordConfirmationRequired
     * @AuthorizedAdminSetting(settings=OCA\Workspace\Settings\WorkspaceAdmin)
     * @NoCSRFRequired
     */
    public function saveSettings($mySetting) {
        return $mySetting;
    }
}