<?php

declare(strict_types=1);

namespace Models\VisualConsole\Items;
use Models\VisualConsole\Item;

/**
 * Model of a Box item of the Visual Console.
 */
final class Box extends Item
{


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
        $boxData = parent::decode($data);
        $boxData['type'] = BOX_ITEM;
        $boxData['parentId'] = null;
        $boxData['aclGroupId'] = null;
        $boxData['borderWidth'] = $this->extractBorderWidth($data);
        $boxData['borderColor'] = $this->extractBorderColor($data);
        $boxData['fillColor'] = $this->extractFillColor($data);
        return $boxData;
    }


    /**
     * Extract a border width value.
     *
     * @param array $data Unknown input data structure.
     *
     * @return integer Valid border width. 0 by default.
     */
    private function extractBorderWidth(array $data): int
    {
        return static::parseIntOr(
            static::issetInArray($data, ['borderWidth', 'border_width']),
            0
        );
    }


    /**
     * Extract a border color value.
     *
     * @param array $data Unknown input data structure.
     *
     * @return mixed String representing the border color (not empty) or null.
     */
    private function extractBorderColor(array $data)
    {
        return static::notEmptyStringOr(
            static::issetInArray($data, ['borderColor', 'border_color']),
            null
        );
    }


    /**
     * Extract a fill color value.
     *
     * @param array $data Unknown input data structure.
     *
     * @return mixed String representing the fill color (not empty) or null.
     */
    private function extractFillColor(array $data)
    {
        return static::notEmptyStringOr(
            static::issetInArray($data, ['fillColor', 'fill_color']),
            null
        );
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
        // Default values.
        $values = static::getDefaultGeneralValues($values);

        if ($values['tabSelected'] === 'general') {
            $inputs[] = [
                'arguments' => [
                    'type'  => 'hidden',
                    'name'  => 'tabGeneral',
                    'value' => true,
                ],
            ];

            // Size.
            $inputs[] = [
                'block_id'      => 'size-item',
                'class'         => 'flex-row flex-start w100p',
                'direct'        => 1,
                'block_content' => [
                    [
                        'label' => __('Size'),
                    ],
                    [
                        'label'     => __('width'),
                        'arguments' => [
                            'name'   => 'width',
                            'type'   => 'number',
                            'value'  => $values['width'],
                            'return' => true,
                        ],
                    ],
                    [
                        'label'     => __('height'),
                        'arguments' => [
                            'name'   => 'height',
                            'type'   => 'number',
                            'value'  => $values['height'],
                            'return' => true,
                        ],
                    ],
                ],
            ];

            // Position.
            $inputs[] = [
                'block_id'      => 'position-item',
                'class'         => 'flex-row flex-start w100p',
                'direct'        => 1,
                'block_content' => [
                    [
                        'label' => __('Position'),
                    ],
                    [
                        'label'     => __('X'),
                        'arguments' => [
                            'name'   => 'x',
                            'type'   => 'number',
                            'value'  => $values['x'],
                            'return' => true,
                        ],
                    ],
                    [
                        'label'     => __('Y'),
                        'arguments' => [
                            'name'   => 'y',
                            'type'   => 'number',
                            'value'  => $values['y'],
                            'return' => true,
                        ],
                    ],
                ],
            ];

            // Show on top.
            $inputs[] = [
                'label'     => __('Show on top'),
                'arguments' => [
                    'name'  => 'isOnTop',
                    'id'    => 'isOnTop',
                    'type'  => 'switch',
                    'value' => $values['isOnTop'],
                ],
            ];
        }

        // Default specific values.
        if (isset($values['borderColor']) === false) {
            $values['borderColor'] = '#000000';
        }

        if (isset($values['borderWidth']) === false) {
            $values['borderWidth'] = 1;
        }

        if (isset($values['fillColor']) === false) {
            $values['fillColor'] = '#ffffff';
        }

        if ($values['tabSelected'] === 'specific') {
            // Border color.
            $inputs[] = [
                'label'     => __('Border color'),
                'arguments' => [
                    'wrapper' => 'div',
                    'name'    => 'borderColor',
                    'type'    => 'color',
                    'value'   => $values['borderColor'],
                    'return'  => true,
                ],
            ];

            // Border Width.
            $inputs[] = [
                'label'     => __('Border Width'),
                'arguments' => [
                    'name'   => 'borderWidth',
                    'type'   => 'number',
                    'value'  => $values['borderWidth'],
                    'return' => true,
                ],
            ];

            // Fill color.
            $inputs[] = [
                'label'     => __('Fill color'),
                'arguments' => [
                    'wrapper' => 'div',
                    'name'    => 'fillColor',
                    'type'    => 'color',
                    'value'   => $values['fillColor'],
                    'return'  => true,
                ],
            ];
        }

        return $inputs;
    }


    /**
     * Default values.
     *
     * @param array $values Array values.
     *
     * @return array Array with default values.
     *
     * @overrides Item->getDefaultGeneralValues.
     */
    public function getDefaultGeneralValues(array $values): array
    {
        // Retrieve global - common inputs.
        $values = parent::getDefaultGeneralValues($values);

        // Default values.
        if (isset($values['width']) === false) {
            $values['width'] = 100;
        }

        if (isset($values['height']) === false) {
            $values['height'] = 100;
        }

        return $values;
    }


}
