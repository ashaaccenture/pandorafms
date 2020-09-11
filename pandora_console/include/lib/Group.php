<?php
/**
 * Group entity class.
 *
 * @category   Class
 * @package    Pandora FMS
 * @subpackage OpenSource
 * @version    1.0.0
 * @license    See below
 *
 *    ______                 ___                    _______ _______ ________
 *   |   __ \.-----.--.--.--|  |.-----.----.-----. |    ___|   |   |     __|
 *  |    __/|  _  |     |  _  ||  _  |   _|  _  | |    ___|       |__     |
 * |___|   |___._|__|__|_____||_____|__| |___._| |___|   |__|_|__|_______|
 *
 * ============================================================================
 * Copyright (c) 2005-2019 Artica Soluciones Tecnologicas
 * Please see http://pandorafms.org for full contribution list
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation for version 2.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * ============================================================================
 */

// Begin.
namespace PandoraFMS;

global $config;
require_once $config['homedir'].'/include/functions_groups.php';

/**
 * PandoraFMS Group entity.
 */
class Group extends Entity
{

    /**
     * List of available ajax methods.
     *
     * @var array
     */
    private static $ajaxMethods = ['getGroupsForSelect'];


    /**
     * Builds a PandoraFMS\Group object from a group id.
     *
     * @param integer $id_group  Group Id.
     * @param boolean $recursive Create parents as objects.
     */
    public function __construct(?int $id_group=null, bool $recursive=false)
    {
        if ($id_group === 0) {
            parent::__construct('tgrupo');

            $this->fields['id'] = 0;
            $this->fields['nombre'] = 'All';
        } else if (is_numeric($id_group) === true) {
            parent::__construct('tgrupo', ['id_grupo' => $id_group]);
            if ($recursive === true) {
                // Customize certain fields.
                $this->fields['parent'] = new Group($this->fields['parent']);
            }
        } else {
            // Empty skel.
            parent::__construct('tgrupo');
        }

    }


    /**
     * Return an array of ids with all children
     *
     * @param boolean $ids_only               Return an array of id_groups or
     *                                        entire rows.
     * @param boolean $ignorePropagationState Search all children ignoring or
     *                                        depending on propagate_acl flag.
     *
     * @return array With all children.
     */
    public function getChildren(
        bool $ids_only=false,
        bool $ignorePropagationState=false
    ):array {
        $available_groups = \groups_get_children(
            $this->id_grupo(),
            $ignorePropagationState
        );

        if (is_array($available_groups) === false) {
            return [];
        }

        if ($ids_only === true) {
            return array_keys($available_groups);
        }

        return $available_groups;

    }


    /**
     * Retrieves a list of groups fitered.
     *
     * @param array $filter Filters to be applied.
     *
     * @return array With all results or false if error.
     * @throws Exception On error.
     */
    private static function search(array $filter):array
    {
        // Default values.
        if (empty($filter['id_user']) === true) {
            // By default query current user groups.
            $filter['id_user'] = false;
        } else if (!\users_is_admin()) {
            // Override user queried if user is not an admin.
            $filter['id_user'] = false;
        }

        if (empty($filter['id_user']) === true) {
            $filter['id_user'] = false;
        }

        if (empty($filter['keys_field']) === true) {
            $filter['keys_field'] = 'id_grupo';
        }

        if (isset($filter['returnAllColumns']) === false) {
            $filter['returnAllColumns'] = true;
        }

        $groups = \users_get_groups(
            $filter['id_user'],
            $filter['privilege'],
            $filter['returnAllGroup'],
            // Return all columns.
            $filter['returnAllColumns'],
            // Field id_groups is not being used anymore.
            null,
            $filter['keys_field'],
            // Cache.
            true,
            // Search term.
            $filter['search']
        );

        if (is_array($groups) === false) {
            return [];
        }

        return $groups;

    }


    /**
     * Returns an hierarchical ordered array.
     *
     * @param array $groups All groups available.
     *
     * @return array Groups ordered.
     */
    private static function prepareGroups(array $groups):array
    {
        $return = [];
        $groups = \groups_get_groups_tree_recursive($groups);
        foreach ($groups as $k => $v) {
            $return[] = [
                'id'    => $k,
                'text'  => \io_safe_output(
                    \ui_print_truncate_text(
                        $v['nombre'],
                        GENERIC_SIZE_TEXT,
                        false,
                        true,
                        false
                    )
                ),
                'level' => $v['deep'],
            ];
        }

        return $return;

    }


    /**
     * Saves current group definition to database.
     *
     * @return mixed Affected rows of false in case of error.
     * @throws \Exception On error.
     */
    public function save()
    {
        global $config;

        if (isset($config['centralized_management']) === true
            && $config['centralized_management'] > 0
        ) {
            throw new \Exception(
                get_class($this).' error, cannot be modified while centralized management environment.'
            );
        }

        if ($this->fields['id_grupo'] > 0) {
            $updates = $this->fields;
            if (is_numeric($updates['parent']) === false) {
                $updates['parent'] = $this->parent()->id_grupo();
            }

            return db_process_sql_update(
                'tgrupo',
                $this->fields,
                ['id_grupo' => $this->fields['id_grupo']]
            );
        }

        return false;
    }


    /**
     * Return error message to target.
     *
     * @param string $msg Error message.
     *
     * @return void
     */
    public static function error(string $msg)
    {
        echo json_encode(['error' => $msg]);
    }


    /**
     * Verifies target method is allowed to be called using AJAX call.
     *
     * @param string $method Method to be invoked via AJAX.
     *
     * @return boolean Available (true), or not (false).
     */
    public static function ajaxMethod(string $method):bool
    {
        return in_array($method, self::$ajaxMethods) === true;
    }


    /**
     * This method is being invoked by select2 to improve performance while
     * installation has a lot of groups (more than 5k).
     *
     * Security applied in controller include/ajax/group.php.
     *
     * @return void
     * @throws \Exception On error.
     */
    public static function getGroupsForSelect()
    {
        $id_user = get_parameter('id_user', false);
        $privilege = get_parameter('privilege', 'AR');
        $returnAllGroup = get_parameter('returnAllGroup', false);
        $id_group = get_parameter('id_group', false);
        $keys_field = get_parameter('keys_field', 'id_grupo');
        $search = get_parameter('search', '');
        $step = get_parameter('step', 1);
        $limit = get_parameter('limit', false);
        $exclusions = get_parameter('exclusions', '[]');
        $inclusions = get_parameter('inclusions', '[]');

        $groups = self::search(
            [
                'id_user'          => $id_user,
                'privilege'        => $privilege,
                'returnAllGroup'   => $returnAllGroup,
                'returnAllColumns' => true,
                'id_group'         => $id_group,
                'keys_field'       => $keys_field,
                'search'           => $search,
            ]
        );

        $exclusions = json_decode(\io_safe_output($exclusions));
        if (empty($exclusions) === false) {
            foreach ($exclusions as $ex) {
                unset($groups[$ex]);
            }
        }

        $inclusions = json_decode(\io_safe_output($inclusions));
        if (empty($inclusions) === false) {
            foreach ($inclusions as $g) {
                if (empty($groups[$g]) === true) {
                    $groups[$g] = \groups_get_name($g);
                }
            }
        }

        $return = self::prepareGroups($groups);

        if (is_array($return) === false) {
            return;
        }

        // Use global block size configuration.
        global $config;
        $limit = $config['block_size'];
        $offset = (($step - 1) * $limit);

        // Pagination over effective groups retrieved.
        // Calculation is faster than transference.
        $count = count($return);
        if (is_numeric($offset) === true && $offset >= 0) {
            if (is_numeric($limit) === true && $limit > 0) {
                $return = array_splice($return, $offset, $limit);
            }
        }

        if ($step > 2) {
            $processed = (($step - 2) * $limit);
        } else {
            $processed = 0;
        }

        $current_ammount = (count($return) + $processed);

        echo json_encode(
            [
                'results'    => $return,
                'pagination' => [
                    'more' => $current_ammount < $count,
                ],
            ]
        );
    }


}
