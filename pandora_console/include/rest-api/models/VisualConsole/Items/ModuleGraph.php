<?php

declare(strict_types=1);

namespace Models\VisualConsole\Items;
use Models\VisualConsole\Item;

/**
 * Model of a module graph item of the Visual Console.
 */
final class ModuleGraph extends Item
{

    /**
     * Used to enable the fetching, validation and extraction of information
     * about the linked module.
     *
     * @var boolean
     */
    protected static $useLinkedModule = true;

    /**
     * Used to enable the fetching, validation and extraction of information
     * about the linked visual console.
     *
     * @var boolean
     */
    protected static $useLinkedVisualConsole = true;

    /**
     * Used to enable validation, extraction and encodeing of the HTML output.
     *
     * @var boolean
     */
    protected static $useHtmlOutput = true;


    /**
     * Returns a valid representation of the model.
     *
     * @param array $data Input data.
     *
     * @return array Data structure representing the model.
     *
     * @overrides Item::decode.
     */
    protected function decode(array $data): array
    {
        $return = parent::decode($data);
        $return['type'] = MODULE_GRAPH;
        $return['backgroundType'] = static::extractBackgroundType($data);
        $return['period'] = static::extractPeriod($data);

        $customGraphId = static::extractCustomGraphId($data);

        if (empty($customGraphId) === false) {
            $return['customGraphId'] = $customGraphId;
        } else {
            $return['graphType'] = static::extractGraphType($data);
        }

        return $return;
    }


    /**
     * Extract a background type value.
     *
     * @param array $data Unknown input data structure.
     *
     * @return string 'transparent', 'white' or 'black'. 'transparent' by default.
     */
    private static function extractBackgroundType(array $data): string
    {
        $value = static::issetInArray($data, ['backgroundType', 'image']);

        switch ($value) {
            case 'transparent':
            case 'white':
            case 'black':
            return $value;

            default:
            return 'transparent';
        }
    }


    /**
     * Extract a graph period value.
     *
     * @param array $data Unknown input data structure.
     *
     * @return mixed The time in seconds of the graph period or null.
     */
    private static function extractPeriod(array $data)
    {
        return static::parseIntOr(
            static::issetInArray($data, ['period']),
            null
        );
    }


    /**
     * Extract a custom graph Id value.
     *
     * @param array $data Unknown input data structure.
     *
     * @return mixed The custom graph Id (int) or null.
     */
    private static function extractCustomGraphId(array $data)
    {
        return static::parseIntOr(
            static::issetInArray($data, ['customGraphId', 'id_custom_graph']),
            null
        );
    }


    /**
     * Extract a graph type value.
     *
     * @param array $data Unknown input data structure.
     *
     * @return string 'line' or 'area'. 'line' by default.
     */
    private static function extractGraphType(array $data): string
    {
        $value = static::issetInArray($data, ['graphType', 'type_graph']);

        switch ($value) {
            case 'line':
            case 'area':
            return $value;

            default:
            return 'line';
        }
    }


    /**
     * Fetch a vc item data structure from the database using a filter.
     *
     * @param array $filter Filter of the Visual Console Item.
     *
     * @return array The Visual Console Item data structure stored into the DB.
     * @throws \InvalidArgumentException When an agent Id cannot be found.
     *
     * @override Item::fetchDataFromDB.
     */
    protected static function fetchDataFromDB(array $filter): array
    {
        // Due to this DB call, this function cannot be unit tested without
        // a proper mock.
        $data = parent::fetchDataFromDB($filter);

        /*
         * Retrieve extra data.
         */

        // Load side libraries.
        global $config;
        include_once $config['homedir'].'/include/functions_graph.php';
        include_once $config['homedir'].'/include/functions_modules.php';
        if (is_metaconsole()) {
            \enterprise_include_once('include/functions_metaconsole.php');
        }

        $imageOnly = false;

        $backgroundType = static::extractBackgroundType($data);
        $period = static::extractPeriod($data);
        $customGraphId = static::extractCustomGraphId($data);
        $graphType = static::extractGraphType($data);
        $linkedModule = static::extractLinkedModule($data);
        $moduleId = $linkedModule['moduleId'];
        $metaconsoleId = $linkedModule['metaconsoleId'];

        // Maybe connect to node.
        $nodeConnected = false;
        if (\is_metaconsole() === true && $metaconsoleId !== null) {
            $nodeConnected = \metaconsole_connect(
                null,
                $metaconsoleId
            ) === NOERR;

            if ($nodeConnected === false) {
                throw new \InvalidArgumentException(
                    'error connecting to the node'
                );
            }
        }

        /*
         * About the 30 substraction to the graph height:
         * The function which generates the graph doesn't respect the
         * required height. It uses it for the canvas (the graph itself and
         * their axes), but then it adds the legend. One item of the legend
         * (one dataset) is about 30px, so we need to substract that height
         * from the canvas to try to fit the element's height.
         *
         * PD: The custom graphs can have more datasets, but we only substract
         * the height of one of it to replicate the legacy functionality.
         */

        // Custom graph.
        if (empty($customGraphId) === false) {
            $customGraph = \db_get_row('tgraph', 'id_graph', $customGraphId);

            if ($data['width'] < 440) {
                $data['width'] = 440;
            }

            if ($data['height'] < 220) {
                $data['height'] = 220;
            }

            $params = [
                'period'          => $period,
                'width'           => (int) $data['width'],
                'height'          => ($data['height'] - 40),
                'title'           => '',
                'unit_name'       => null,
                'show_alerts'     => false,
                'only_image'      => $imageOnly,
                'vconsole'        => true,
                'document_ready'  => false,
                'backgroundColor' => $backgroundType,
            ];

            $paramsCombined = [
                'id_graph'       => $customGraphId,
                'stacked'        => $customGraph['stacked'],
                'summatory'      => $customGraph['summatory_series'],
                'average'        => $customGraph['average_series'],
                'modules_series' => $customGraph['modules_series'],
            ];

            $data['html'] = \graphic_combined_module(
                false,
                $params,
                $paramsCombined
            );
        } else {
            // Module graph.
            if ($moduleId === null) {
                throw new \InvalidArgumentException('missing module Id');
            }

            if ($data['width'] < 440) {
                $data['width'] = 440;
            }

            if ($data['height'] < 220) {
                $data['height'] = 220;
            }

            $params = [
                'agent_module_id' => $moduleId,
                'period'          => $period,
                'show_events'     => false,
                'width'           => (int) $data['width'],
                'height'          => ($data['height'] - 40),
                'title'           => \modules_get_agentmodule_name($moduleId),
                'unit'            => \modules_get_unit($moduleId),
                'only_image'      => $imageOnly,
                'menu'            => false,
                'backgroundColor' => $backgroundType,
                'type_graph'      => $graphType,
                'vconsole'        => true,
                'document_ready'  => false,
            ];

            $data['html'] = \grafico_modulo_sparse($params);
        }

        // Restore connection.
        if ($nodeConnected === true) {
            \metaconsole_restore_db();
        }

        return $data;
    }


    /**
     * Return List custom graph.
     *
     * @return array
     */
    public function getListCustomGraph():array
    {
        include_once 'include/functions_custom_graphs.php';
        enterprise_include_once('include/functions_metaconsole.php');
        $data = [];
        if (is_metaconsole() === true) {
            $data = metaconsole_get_custom_graphs(true);
        } else {
            $data = custom_graphs_get_user(
                $config['id_user'],
                false,
                true,
                'RR'
            );
        }

        $data[0] = __('None');

        return array_reverse($data);
    }


    /**
     * Generates inputs for form (specific).
     *
     * @param array $values Default values.
     *
     * @return array Of inputs.
     *
     * @throws Exception On error.
     */
    public static function getFormInputs(array $values): array
    {
        // Retrieve global - common inputs.
        $inputs = Item::getFormInputs($values);

        if (is_array($inputs) !== true) {
            throw new Exception(
                '[ModuleGraph]::getFormInputs parent class return is not an array'
            );
        }

        if ($values['tabSelected'] === 'specific') {
            // Background color.
            $fields = [
                'white'       => __('White'),
                'black'       => __('Black'),
                'transparent' => __('Transparent'),
            ];

            $inputs[] = [
                'label'     => __('Background color'),
                'arguments' => [
                    'type'     => 'select',
                    'fields'   => $fields,
                    'name'     => 'backgroundType',
                    'selected' => $values['backgroundType'],
                    'return'   => true,
                    'sort'     => false,
                ],
            ];

            $hiddenModule = false;
            $hiddenCustom = true;
            $checkedModule = true;
            $checkedCustom = false;
            if (isset($values['customGraphId']) === true
                && $values['customGraphId'] !== 0
            ) {
                $hiddenModule = true;
                $hiddenCustom = false;
                $checkedModule = false;
                $checkedCustom = true;
            }

            // Choose Type module graph if graph normal or custom.
            $inputs[] = [
                'wrapper'       => 'div',
                'class'         => 'flex-row-vcenter',
                'direct'        => 1,
                'block_content' => [
                    [
                        'label'     => __('Module Graph'),
                        'arguments' => [
                            'type'         => 'radio_button',
                            'attributes'   => 'class="btn"',
                            'name'         => 'choosetype',
                            'value'        => 'module',
                            'checkedvalue' => $checkedModule,
                            'script'       => 'typeModuleGraph(\'module\')',
                            'return'       => true,
                        ],
                    ],
                    [
                        'label'     => __('Custom Graph'),
                        'arguments' => [
                            'type'         => 'radio_button',
                            'attributes'   => 'class="btn"',
                            'name'         => 'choosetype',
                            'value'        => 'custom',
                            'checkedvalue' => $checkedCustom,
                            'script'       => 'typeModuleGraph(\'custom\')',
                            'return'       => true,
                        ],
                    ],
                ],
            ];

            // Autocomplete agents.
            $inputs[] = [
                'id'        => 'MGautoCompleteAgent',
                'hidden'    => $hiddenModule,
                'label'     => __('Agent'),
                'arguments' => [
                    'type'               => 'autocomplete_agent',
                    'name'               => 'agentAlias',
                    'id_agent_hidden'    => $values['agentId'],
                    'name_agent_hidden'  => 'agentId',
                    'server_id_hidden'   => $values['metaconsoleId'],
                    'name_server_hidden' => 'metaconsoleId',
                    'return'             => true,
                    'module_input'       => true,
                    'module_name'        => 'moduleId',
                    'module_none'        => 'false',
                ],
            ];

            // Autocomplete module.
            $inputs[] = [
                'id'        => 'MGautoCompleteModule',
                'hidden'    => $hiddenModule,
                'label'     => __('Module'),
                'arguments' => [
                    'type'           => 'autocomplete_module',
                    'fields'         => $fields,
                    'name'           => 'moduleId',
                    'selected'       => $values['moduleId'],
                    'return'         => true,
                    'sort'           => false,
                    'agent_id'       => $values['agentId'],
                    'metaconsole_id' => $values['metaconsoleId'],
                ],
            ];

            // Custom graph.
            $fields = self::getListCustomGraph();
            $inputs[] = [
                'id'        => 'MGcustomGraph',
                'hidden'    => $hiddenCustom,
                'label'     => __('Custom graph'),
                'arguments' => [
                    'type'     => 'select',
                    'fields'   => $fields,
                    'name'     => 'customGraphId',
                    'selected' => $values['customGraphId'],
                    'return'   => true,
                ],
            ];

            // Graph Type.
            $fields = [
                'line' => __('Line'),
                'area' => __('Area'),
            ];

            $inputs[] = [
                'label'     => __('Graph Type'),
                'arguments' => [
                    'type'     => 'select',
                    'fields'   => $fields,
                    'name'     => 'graphType',
                    'selected' => $values['graphType'],
                    'return'   => true,
                ],
            ];

            // Inputs LinkedVisualConsole.
            $inputsLinkedVisualConsole = self::inputsLinkedVisualConsole(
                $values
            );
            foreach ($inputsLinkedVisualConsole as $key => $value) {
                $inputs[] = $value;
            }
        }

        return $inputs;
    }


}
