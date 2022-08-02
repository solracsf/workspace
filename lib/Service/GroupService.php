<?php

/**
 * @copyright Copyright (c) 2017 Arawa
 *
 * @author 2022 Baptiste Fotia <baptiste.fotia@arawa.fr>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Workspace\Service;

use OCP\IGroupManager;

class GroupService {

    /** @var IGroupManager */
    private $groupManager;
    
    public function __construct(IGroupManager $groupManager)
    {
        $this->groupManager = $groupManager;
    }

    /** 
     * @param String[] $backends
     * @return String||null return the Group's backend (example : database or ldap) 
     * or null if it's not exist.
     */
    private function getTypeBackend($backends) {
        $backend = array_filter($backends, function($backend) {
            if (strtolower($backend) === "database" || strtolower($backend) === "ldap") {
                return $backend;
            }
            return null;
        });
        return $backend[0];
    }

    /**
     * @param String[] $backends
     * @return boolean return false if the backend is database or true if it's other.
     */
    private function checkLocked($backends) {
        $backend = $this->getTypeBackend($backends);

        if (strtolower($backend) !== 'database') {
            return true;
        }

        return false;
    }

    /**
     * @return array return a groups associative array
     */
    public function getAll() {
        $groups = [];
        foreach($this->groupManager->search('') as $group) {
            $groups[] = [
                'gid' => $group->getGID(),
                'display_name' => $group->getDisplayName(),
                'is_locked' => $this->checkLocked($group->getBackendNames()),
                'backend' => $this->getTypeBackend($group->getBackendNames())
            ];
        }

        return $groups;
    }

}