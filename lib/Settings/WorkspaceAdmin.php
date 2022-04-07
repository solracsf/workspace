<?php

namespace OCA\Workspace\Settings;

use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\IDelegatedSettings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;

class WorkspaceAdmin implements IDelegatedSettings {


    /** @var IL10N */
    private $l;

    /** @var IURLGenerator */
    private $urlGenerator;

    /** @var IConfig */
    private $config;

    public function __construct(IConfig $config, IL10N $l) {
        $this->config = $config;
        $this->l = $l;
    }
    
    public function getForm() {
		return new TemplateResponse(
			'workspace',
			'admin',
			['appId' => 'workspace'],
		);
	}

    public function getSection() {
		return 'workspace';
	}

	public function getPriority() {
		return 33;
	}

    public function getName(): ?string {
        return '';
        // return $this->l->t('Workspace settings');
        // return $this->l->t('Workspace settings');
    }

    public function getAuthorizedAppConfig(): array {
        return [];
    }

}