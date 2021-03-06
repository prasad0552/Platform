<?php

namespace Orchid\Platform\Behaviors;

use Illuminate\Support\Str;
use Orchid\Platform\Exceptions\TypeException;

class Helpers
{

    /**
     * Generate a ready-made html form for display to the user
     *
     * @param array $fields
     * @param null $post
     *
     * @param string $language
     * @param string $globalPrefix
     *
     * @return string
     * @throws TypeException
     */
    public static function generateForm(
        array $fields,
        $post,
        string $language = 'en',
        $globalPrefix = 'content'
    ) : string {
        $fields = self::parseFields($fields);
        $availableFormFields = [];

        $form = '';
        foreach ($fields as $field => $config) {
            $fieldClass = config('platform.fields.' . $config['tag']);

            if (is_null($fieldClass)) {
                throw new TypeException('Field ' . $config['tag'] . ' does not exist');
            }

            $field = new $fieldClass();
            $config['lang'] = $language;

            if (isset($config['prefix'])) {
                $prefixArray = array_filter(explode(' ', $config['prefix']));

                foreach ($prefixArray as $prefix) {
                    $config['prefix'] .= '[' . $prefix . ']';
                }
            } else {
                $config['prefix'] = $globalPrefix;
            }

            if (isset($config['name'])) {
                $nameArray = array_filter(explode(' ', $config['name']));

                if (count($nameArray) > 1) {
                    $config['name'] = '';

                    if (!is_null($post)) {
                        $config['value'] = $post->getContent($nameArray[0], $language);
                    }

                    foreach ($nameArray as $name) {
                        $config['name'] .= '[' . $name . ']';
                        if (!is_null($post) && !is_null($config['value']) && is_array($config['value']) && array_key_exists($name,
                                $config['value'])
                        ) {
                            $config['value'] = $config['value'][$name];
                        }
                    }
                } else {
                    if (!is_null($post)) {
                        $config['value'] = $post->getContent($config['name'], $language);
                    }
                    $config['name'] = '[' . $config['name'] . ']';
                }
            }

            $firstTimeRender = false;
            if (!in_array($fieldClass, $availableFormFields)) {
                array_push($availableFormFields, $fieldClass);
                $firstTimeRender = true;
            }

            $field = $field->create($config, $firstTimeRender);
            $form .= $field->render();
        }

        return $form;
    }

    /**
     * Parse the data fields.
     *
     * @param $data
     *
     * @return array
     */
    public static function parseFields($data)
    {
        $data = self::explodeFields($data);
        //string parse
        foreach ($data as $name => $value) {
            $newField = collect();
            foreach ($value as $rule) {
                if (array_key_exists(0, $value)) {
                    $newField[] = self::parseStringFields($rule);
                }
            }
            $fields[$name] = $newField->collapse();
        }
        //parse array
        foreach ($data as $name => $value) {
            if (!array_key_exists(0, $value)) {
                $fields[$name] = collect($value);
            }
        }

        return $fields ?? [];
    }

    /**
     * Explode the rules into an array of rules.
     *
     * @param array $rules
     *
     * @return array
     */
    public static function explodeFields(array $rules) : array
    {
        foreach ($rules as $key => $rule) {
            if (Str::contains($key, '*')) {
                $rules->each($key, [$rule]);
                unset($rules[$key]);
            } else {
                if (is_string($rule)) {
                    $rules[$key] = explode('|', $rule);
                } elseif (is_object($rule)) {
                    $rules[$key] = [$rule];
                } else {
                    $rules[$key] = $rule;
                }
            }
        }

        return $rules;
    }

    /**
     * @param $rules
     *
     * @return array
     */
    public static function parseStringFields(string $rules) : array
    {
        $parameters = [];
        // The format for specifying validation rules and parameters follows an
        // easy {rule}:{parameters} formatting convention. For instance the
        // rule "Max:3" states that the value may only be three letters.
        if (strpos($rules, ':') !== false) {
            list($rules, $parameter) = explode(':', $rules, 2);
            $parameters = self::parseParameters($rules, $parameter);
        }

        return [
            $rules => empty($parameters) ? true : implode(' ', $parameters),
        ];
    }

    /**
     * Parse a parameter list.
     *
     * @param string $rule
     * @param string $parameter
     *
     * @return array
     */
    public static function parseParameters(string $rule, string $parameter) : array
    {
        if (strtolower($rule) == 'regex') {
            return [$parameter];
        }

        return str_getcsv($parameter);
    }
}
