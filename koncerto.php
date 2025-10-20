<?php

// phpcs:disable PSR1.Classes.ClassDeclaration

/**
 * Koncerto Framework
 * This is Koncerto Framework main class
 */
class Koncerto
{
    /**
     * @var array<string, string|array>
    */
    // @phpstan-ignore missingType.iterableValue
    private static $config = array();

    /**
     * Use Koncerto from source with autoload
     *
     * @return void
     */
    public static function autoload()
    {
        spl_autoload_register('Koncerto::loadClass');
    }

    /**
     * Load class from source when using Koncerto with autoload
     *
     * @param  string $className
     * @return void
     */
    public static function loadClass($className)
    {
        if (class_exists($className)) {
            return;
        }

        if ('clsTinyButStrong' === $className && !class_exists('clsTinyButStrong')) {
            self::loadTBS();
            return;
        }

        $classFile = sprintf('%s/%s.php', dirname(__FILE__), $className);
        $root = is_string($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '.';

        if (!is_file($classFile)) {
            $classFile = sprintf('%s/%s.php', $root, $className);
        }

        if (!is_file($classFile) && 'Controller' === substr($className, -10)) {
            $classFile = sprintf('%s/_controller/%s.php', $root, $className);
        }

        if (!is_file($classFile)) {
            throw new Exception(sprintf('Class file [%s] not found', $classFile));
        }

        include_once $classFile;
    }

    /**
     * @param  array<string, string|array<string, string>> $config
     * @return void
     */
    public static function setConfig($config)
    {
        Koncerto::$config = $config;
    }

    /**
     * @param  string $entry
     * @return ?string
     */
    public static function getConfig($entry)
    {
        $config = Koncerto::$config;

        $path = explode('.', $entry);

        // @phpstan-ignore notIdentical.alwaysTrue
        while (false !== ($subentry = array_shift($path))) {
            if (!is_array($config) || !array_key_exists($subentry, $config)) {
                $config = null;

                break;
            }

            $config = $config[$subentry];
            if (0 === count($path)) {
                break;
            }
        }

        return is_string($config) ? $config : null;
    }

    /**
     * Static function to return response from Koncerto Framework
     *
     * @return string
     */
    public static function response()
    {
        $request = new KoncertoRequest();
        $router = new KoncertoRouter();
        $pathInfo = $request->getPathInfo();
        $match = $router->match($pathInfo);
        if (null === $match && '.php' !== strrchr($pathInfo, '.') && is_file('.' . $pathInfo)) {
            return (string)file_get_contents('.' . $pathInfo);
        }
        if (null === $match) {
            throw new Exception(sprintf('No match for route %s', $pathInfo));
        }
        list($controller, $action) = explode('::', $match);
        $classFile = sprintf('%s/_controller/%s.php', dirname(__FILE__), $controller);
        if (!class_exists($controller) && is_file($classFile)) {
            include_once $classFile;
        }
        $root = is_string($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '.';
        $classFile = sprintf('%s/_controller/%s.php', $root, $controller);
        if (!class_exists($controller) && is_file($classFile)) {
            include_once $classFile;
        }
        $response = (new $controller())->$action();
        $headers = $response->getHeaders();
        foreach ($headers as $headerName => $headerValue) {
            header(sprintf('%s: %s', $headerName, $headerValue));
        }

        return $response->getContent();
    }

    /**
     * @return void
     */
    private static function loadTBS()
    {
        $tbsLocations = array(
            dirname(__FILE__) . '/tbs_class.php',
            dirname(__FILE__) . '/../tbs_class.php',
            dirname(__FILE__) . '/tinybutstrong/tbs_class.php',
            dirname(__FILE__) . '/../tinybutstrong/tbs_class.php'
        );

        foreach ($tbsLocations as $tbsLocation) {
            if (is_file($tbsLocation)) {
                include_once $tbsLocation;

                return;
            }
        }
    }

    /**
     * Parse internal comment
     *
     * @param string|false $comment
     * @return array<array-key, mixed>
     */
    public static function getInternal($comment)
    {
        if (false === $comment) {
            return array();
        }

        $lines = explode("\n", $comment);
        foreach ($lines as $line) {
            $line = trim($line);
            // @phpstan-ignore argument.sscanf
            if (2 === sscanf($line, "%*[^@]@internal %[^\n]s", $json)) {
                $internal = (array)json_decode((string)$json, true);
                return $internal;
            }
        }

        return array();
    }

    /**
     * Set/update cache and optionnaly returns a specific key from this cache
     *
     * @param string $name The cache filenale
     * @param ?string $return The key from cache to return
     * @param array<array-key, mixed> $data The data to put in cache, reads from cache if empty
     * @param ?string $source Source directory or file to invalidate cache
     * @return string|array<array-key, mixed>|null
     */
    public static function cache($name, $return = null, $data = array(), $source = null)
    {
        $result = null;

        $cacheDir = '_cache/';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir);
        }
        $cacheFile = $cacheDir . $name . '.json';
        if (!is_file($cacheFile)) {
            file_put_contents($cacheFile, '[]');
        }

        $cache = (array)json_decode((string)file_get_contents($cacheFile), true);

        if (null !== $source && (is_dir($source) || is_file($source))) {
            $stat = stat($source);
            if (false !== $stat && $stat[9] >= filemtime($cacheFile)) {
                $cache = array();
            }
        }

        $cache = array_merge($cache, $data);

        if (file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT))) {
            $result = $cache;
        }

        if (null === $return) {
            return $result;
        }

        if (!array_key_exists($return, $data)) {
            return null;
        }

        $result = $data[$return];

        if (is_string($result) || is_array($result)) {
            return $result;
        }

        return null;
    }
}


// phpcs:disable PSR1.Classes.ClassDeclaration

/**
 *  Base (and Helper) class for Koncerto controllers
 */
class KoncertoController
{
    /**
     * @param  string                $template
     * @param  array<string, mixed>  $context
     * @param  array<string, string> $headers
     * @return KoncertoResponse
     */
    public function render($template, $context = array(), $headers = array())
    {
        Koncerto::loadClass('clsTinyButStrong');

        $tbs = new clsTinyButStrong();
        $tbs->MethodsAllowed = true;
        $tbs->ObjectRef = array();
        $tbs->ObjectRef['request'] = new KoncertoRequest();
        $tbs->SetOption('include_path', dirname(__FILE__) . '/_templates');
        $tbs->SetOption('include_path', dirname(__FILE__) . '/../_templates');
        $tbs->SetOption('include_path', dirname(__FILE__) . '/..');
        $tbs->SetOption('include_path', dirname(__FILE__));
        $tbs->LoadTemplate($template);

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $tbs->MergeBlock($key, 'array', $value);
                continue;
            }
            if (is_object($value) && is_a($value, 'KoncertoForm')) {
                /**
                 * @var KoncertoForm
                 */
                $form = $value;
                $form->setOption('name', $key);
                $tbs->TplVars[sprintf('forms[%s]', $key)] = $form;
                $dataKey = sprintf('data[%s]', $key);
                $tbs->TplVars[$dataKey] = array();
                foreach ((array)$form->getData() as $k => $v) {
                    $fieldKey = sprintf('field[%s]', $k);
                    $tbs->TplVars[$dataKey][$fieldKey] = $v;
                }
                foreach ($form->getOptions() as $optionName => $optionValue) {
                    $optionKey = sprintf(
                        '%s.%s.%s',
                        $key,
                        'option',
                        $optionName
                    );
                    $tbs->MergeField($optionKey, $optionValue);
                }
                $optionKey = sprintf(
                    '%s.%s',
                    $key,
                    'option'
                );
                $tbs->MergeField($optionKey, '');
                $fieldKey = sprintf('%s.%s', $key, 'field');
                $tbs->MergeBlock($fieldKey, $form->getFields());
                foreach ($form->getFields() as $field) {
                    if ('select' === $field->getType()) {
                        $optionsKey = sprintf(
                            '%s.%s.%s.%s',
                            $key,
                            'field',
                            $field->getName(),
                            'options'
                        );
                        $tbs->MergeBlock($optionsKey, $field->getOptions());
                    }
                }
                continue;
            }
            $tbs->MergeField($key, $value);
        }

        $response = new KoncertoResponse();
        foreach ($headers as $headerName => $headerValue) {
            $response->setHeader($headerName, $headerValue);
        }

        $tbs->Show(TBS_NOTHING);

        return $response->setContent($tbs->Source);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, mixed> $options
     * @return KoncertoResponse
     */
    public function json($data, $options = array())
    {
        $json = (string)json_encode($data);
        if (array_key_exists('pretty', $options) && is_bool($options['pretty']) && true === $options['pretty']) {
            $json = (string)json_encode($data, JSON_PRETTY_PRINT);
        }

        $response = (new KoncertoResponse())->setHeader('Content-type', 'application/json');

        if (array_key_exists('headers', $options) && is_array($options['headers'])) {
            foreach ($options['headers'] as $headerName => $headerValue) {
                if (is_string($headerValue) || is_numeric($headerValue) || is_bool($headerValue)) {
                    $response->setHeader($headerName, (string)$headerValue);
                }
            }
        }

        return $response->setContent($json);
    }

    /**
     * Redirect to another url
     *
     * @param string $url
     * @return KoncertoResponse
     */
    public function redirect($url)
    {
        return (new KoncertoResponse())
            ->setHeader('Location', $url)
            ->setContent(null);
    }

    /**
     * Get the route associated with the controller from internal comment
     *
     * @param class-string $className
     * @return ?string
     */
    public function getRoute($className = null)
    {
        if (null === $className) {
            $className = get_called_class();
        }
        $ref = new ReflectionClass($className);
        $internal = Koncerto::getInternal($ref->getDocComment());

        if (!array_key_exists('route', $internal)) {
            return null;
        }

        if (!array_key_exists('name', $internal['route'])) {
            return null;
        }

        return $internal['route']['name'];
    }
}


// phpcs:disable PSR1.Classes.ClassDeclaration

/**
 * Helper class for ORM
 */
class KoncertoEntity
{
    /**
     * Instantiate entity from array
     *
     * @param  array<string, bool|float|int|string|null> $data
     * @return KoncertoEntity
     */
    public function hydrate($data)
    {
        $id = $this->getId();

        $ref = new ReflectionClass($this);
        $props = $ref->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($props as $prop) {
            $propName = $prop->getName();
            $emptyKey = null !== $id && array_key_exists($id, $data) && empty($data[$propName]);
            if (!$emptyKey && array_key_exists($propName, $data)) {
                $comment = $prop->getDocComment();
                if (false === $comment) {
                    $comment = '';
                }
                $propType = $this->getType($comment);
                $value = $data[$propName];
                if ('?' === substr($propType, 0, 1)) {
                    $propType = substr($propType, 1);
                    if (empty($value)) {
                        $value = null;
                        $propType = 'null';
                    }
                }
                switch ($propType) {
                    case 'bool':
                    case 'boolean':
                        $value = filter_var($value, FILTER_VALIDATE_BOOL);
                        break;
                    case 'float':
                        $value = floatval($value);
                        break;
                    case 'int':
                    case 'integer':
                        $value = intval($value);
                        break;
                    case 'string':
                        $value = strval($value);
                        break;
                }
                $this->$propName = $value;
            }
        }

        return $this;
    }

    /**
     * Transform entity to array
     *
     * @return array<string, bool|float|int|string|null>
     */
    public function serialize()
    {
        $obj = array();

        $ref = new ReflectionClass($this);
        $props = $ref->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($props as $prop) {
            $propName = $prop->getName();
            $obj[$propName] = $this->$propName;
        }

        return $obj;
    }

    /**
     * Perist entity (create or update)
     *
     * @return ?KoncertoEntity
     */
    public function persist()
    {
        // @todo - get entityName and entityManager from entity internal annotation
        $dsn = Koncerto::getConfig('entityManager.default');
        if (null === $dsn) {
            return null;
        }
        $pdo = new PDO($dsn);

        $data = $this->serialize();
        $fields = array_keys($data);
        $placeholders = array_map(
            function ($field) {
                return ':' . $field;
            },
            $fields
        );

        $className = get_class($this);
        $entityName = strtolower($className);

        $id = $this->getId();
        if (null === $id) {
            return null;
        }

        if (empty($data[$id])) {
            $data[$id] = null;

            $query = $pdo->prepare(
                sprintf(
                    'INSERT INTO %s (%s) VALUES (%s)',
                    $entityName,
                    implode(',', $fields),
                    implode(',', $placeholders)
                )
            );
        } else {
            $entity = $this->find($className, strval($data['id']));
            if (null === $entity) {
                return null;
            }
            $updates = array_map(
                function ($field, $placeholder) {
                    return sprintf('%s = %s', $field, $placeholder);
                },
                $fields,
                $placeholders
            );

            $query = $pdo->prepare(
                sprintf(
                    'UPDATE %s SET %s WHERE %s = :%s',
                    $entityName,
                    implode(',', $updates),
                    $id,
                    $id
                )
            );
        }

        $query->execute($data);

        if (!array_key_exists($id, $data) || empty($data[$id])) {
            if (false !== $pdo->lastInsertId()) {
                $data = array($id => $pdo->lastInsertId());
            } else {
                $data = array();
            }
        } else {
            $data = array();
        }

        return $this->hydrate($data);
    }

    /**
     * Remove entity
     *
     * @return boolean
     */
    public function remove()
    {
        // @todo - get entityName and entityManager from entity internal annotation
        $dsn = Koncerto::getConfig('entityManager.default');
        if (null === $dsn) {
            return false;
        }
        $pdo = new PDO($dsn);

        $data = $this->serialize();

        $entityName = strtolower(get_class($this));

        $id = $this->getId();
        if (null === $id) {
            return false;
        }

        $query = $pdo->prepare(
            sprintf(
                'DELETE FROM %s WHERE %s = :%s',
                $entityName,
                $id,
                $id
            )
        );

        return $query->execute(array($id => $data[$id]));
    }

    /**
     * Find entities by class and primary key or criterias
     *
     * @param class-string $class
     * @param array<string, string>|string|int $criteria
     * @return KoncertoEntity|KoncertoEntity[]|null
     */
    public static function find($class, $criteria = array())
    {
        $entities = array();
        // @todo - get entityName and entityManager from entity internal annotation
        $dsn = Koncerto::getConfig('entityManager.default');
        if (null === $dsn) {
            return array();
        }
        $pdo = new PDO($dsn);

        $classFile = sprintf('_entity/%s.php', $class);
        if (!is_file($classFile)) {
            return array();
        }

        include_once $classFile;
        if (!class_exists($class)) {
            return array();
        }

        $entityName = strtolower($class);
        $entityClass = new $class();

        $where = '1 = 1';
        $values = array();

        $findById = is_string($criteria) || is_numeric($criteria);

        if ($findById) {
            /** @var KoncertoEntity $entityClass */
            $id = $entityClass->getId();
            $values = array($id => $criteria);
            $where = sprintf(
                '%s = :%s',
                $id,
                $id
            );
        }

        if (is_array($criteria) && count($criteria) > 0) {
            $conditions = array();
            $values = array();
            foreach ($criteria as $field => $condition) {
                // @todo - allow more conditions (equal, not equal, is null, etc)
                array_push(
                    $conditions,
                    sprintf(
                        '%s %s',
                        $field,
                        ' = :' . $field
                    )
                );
                $values[$field] = $condition;
            }
            $where = implode(' AND ', $conditions);
        }

        $query = $pdo->prepare(
            sprintf(
                'SELECT * FROM %s WHERE %s',
                $entityName,
                $where
            )
        );

        $query->execute($values);

        $result = $query->fetchAll(PDO::FETCH_CLASS, $class);

        if ($findById && count($result) > 1) {
            throw new Exception(sprintf(
                'NonUniqueResult %s for entity %s',
                json_encode($criteria),
                $entityName
            ));
        }

        if ($findById) {
            $result = empty($result) ? null : $result[0];
        }

        return $result;
    }

    /**
     * Get ID column from internal comments
     *
     * @return ?string
     */
    private function getId()
    {
        $className = get_class($this);
        $entity = Koncerto::cache('entities', $className);
        if (is_array($entity) && array_key_exists('id', $entity) && is_string($entity['id'])) {
            return $entity['id'];
        }

        $ref = new ReflectionClass($this);
        $props = $ref->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($props as $prop) {
            $internal = Koncerto::getInternal($prop->getDocComment());
            if (array_key_exists('key', $internal)) {
                Koncerto::cache('entities', null, array($className => array('id' => $prop->getName())));

                return $prop->getName();
            }
        }

        return null;
    }

    /**
     * Get column type from @var comments
     *
     * @param  string $comment
     * @return string
     */
    private function getType($comment)
    {
        $type = 'string';

        $lines = explode("\n", $comment);
        foreach ($lines as $line) {
            $line = trim($line);
            // @phpstan-ignore argument.sscanf
            if (2 === sscanf($line, "%*[^@]@var %[^\n]s", $varType)) {
                $type = (string)$varType;

                break;
            }
        }

        return $type;
    }
}


// phpcs:disable PSR1.Classes.ClassDeclaration

/**
 * This class allows to create enumerations to use with entities and forms
 * Reserved keywords : cases, from
 */
class KoncertoEnum
{
    /**
     * @var array<array-key, bool|float|int|string|null>
     */
    private static $cases = array();

    /**
     * Return the int value of the enum case
     *
     * @param  string       $name
     * @param  array<mixed> $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments = array())
    {
        if (count($arguments) > 0) {
            return null;
        }

        self::parseCases();

        $search = array_search($name, self::$cases);

        if (false === $search) {
            return null;
        }

        return $search;
    }

    /**
     * Return the case name from the value
     *
     * @param  int|string $value
     * @return bool|float|int|string|null
     */
    public static function from($value)
    {
        self::parseCases();

        if (!array_key_exists($value, self::$cases)) {
            return null;
        }

        return self::$cases[$value];
    }

    /**
     * Return the list of cases as name => value
     *
     * @return array<array-key, bool|float|int|string|null>
     */
    public static function cases()
    {
        self::parseCases();

        return self::$cases;
    }

    /**
     * Extract cases from "method" annotations
     *
     * @return void
     */
    private static function parseCases()
    {
        $class = new ReflectionClass(get_called_class());
        $comment = $class->getDocComment();
        if (false === $comment) {
            return;
        }

        self::$cases = array();
        $lines = explode("\n", $comment);
        foreach ($lines as $line) {
            // @phpstan-ignore argument.sscanf
            if (4 === sscanf($line, "%*[^@]@method %s %s %[^\n]s", $type, $name, $value)) {
                $name = preg_replace('/[^A-Za-z0-9]/', '', strval($name));
                $key = json_decode((string)$value);
                if (is_string($key) || is_numeric($key)) {
                    self::$cases[$key] = $name;
                }
            // @phpstan-ignore argument.sscanf
            } elseif (2 === sscanf($line, "%*[^@]@method int %[^\n]s", $name)) {
                $name = preg_replace('/[^A-Za-z0-9]/', '', strval($name));
                array_push(self::$cases, $name);
            }
        }
    }
}


// phpcs:disable PSR1.Classes.ClassDeclaration

/**
 * Helper class to generate form fields based on
 * Template engine and _form.tbs.html template
 */
class KoncertoField
{
    /**
     * @var ?KoncertoForm
     */
    private $form = null;
    /**
     * @var ?string
     */
    private $name = null;
    /**
     * @var string
     */
    private $type = 'text';
    /**
     * @var ?string
     */
    private $label = null;
    /**
     * @var array<array-key, string>
     */
    private $options = array();

    /**
     * @param  KoncertoForm $form
     * @return KoncertoField
     */
    public function setForm($form)
    {
        $this->form = $form;

        return $this;
    }

    /**
     * @return ?KoncertoForm
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @param  ?string $key
     * @return mixed
     */
    public function getData($key = null)
    {
        if (null === $this->form || null === $this->name) {
            return null;
        }

        $data = $this->form->getData();
        if (null === $data) {
            return null;
        }

        if (!is_array($data)) {
            $data = $data->serialize();
        }

        if (!array_key_exists($this->name, $data)) {
            return null;
        }

        return $data[$this->name];
    }

    /**
     * @param  string $name
     * @return KoncertoField
     */
    public function setName($name)
    {
        if ('name' === $name) {
            throw new Exception(sprintf('KoncertoField::setName(%s) - "%s" is a reserved keyword', $name, $name));
        }

        $this->name = $name;

        return $this;
    }

    /**
     * @return ?string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param  string $type
     * @return KoncertoField
     */
    public function setType($type)
    {
        if (!array_key_exists($type, KoncertoFieldType::cases())) {
            throw new Exception(sprintf('Unknown field type %s, expected KoncertoFieldType', $type));
        }

        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param  string $label
     * @return KoncertoField
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return ?string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param  array<array-key, string> $options
     * @return KoncertoField
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<array-key, string>
     */
    public function getOptions()
    {
        return $this->options;
    }
}


// phpcs:disable PSR1.Classes.ClassDeclaration

/**
 * Field type enumeration (hidden, text, etc)
 *
 * @method string Hidden() "hidden"
 * @method string Text() "text"
 * @method string Email() "email"
 * @method string Textarea() "textarea"
 * @method string Select() "select"
 */
class KoncertoFieldType extends KoncertoEnum
{
}


// phpcs:disable PSR1.Classes.ClassDeclaration

/**
 * Helper class to generate and submit forms
 * Requires template engine and _form.tbs.html template
 */
class KoncertoForm
{
    /**
     * @var KoncertoField[]
     */
    private $fields = array();

    /**
     * @var array<string, bool|float|int|string|null|array<array-key, bool|float|int|string|null>>
     */
    private $options = array();

    /**
     * @param  KoncertoField $field
     * @return KoncertoForm
     */
    public function add($field)
    {
        array_push($this->fields, $field);
        $field->setForm($this);

        return $this;
    }

    /**
     * @return KoncertoField[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param  string $optionName
     * @param  bool|float|int|string|null|array<array-key, bool|float|int|string|null> $optionValue
     * @return KoncertoForm
     */
    public function setOption($optionName, $optionValue = null)
    {
        $optionName = strtolower(($optionName));

        if (null === $optionValue && array_key_exists($optionName, $this->options)) {
            unset($this->options[$optionName]);

            return $this;
        }

        $this->options[$optionName] = $optionValue;

        if ('data' === $optionName && is_array($optionValue)) {
            $form = 'form';
            if (array_key_exists('class', $this->options) && is_string($this->options['class'])) {
                $form = strtolower($this->options['class']);
            }
            $request = new KoncertoRequest();
            $request->set($form, $optionValue);
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return string
     */
    public function getName()
    {
        $name = 'form';
        if (array_key_exists('name', $this->options) && !is_array($this->options['name'])) {
            $name = $this->options['name'];
        }

        return (string)$name;
    }

    /**
     * @return bool
     */
    public function isSubmitted()
    {
        $request = new KoncertoRequest();
        $class = 'form';
        if (array_key_exists('class', $this->options) && is_string($this->options['class'])) {
            $class = strtolower($this->options['class']);
        }
        $form = $request->get($class);

        return null !== $form && is_array($form);
    }

    /**
     * @return KoncertoEntity|array<string, bool|float|int|string|null>|null
     */
    public function getData()
    {
        $request = new KoncertoRequest();
        $class = 'form';
        if (array_key_exists('class', $this->options) && is_string($this->options['class'])) {
            $class = strtolower($this->options['class']);
        }

        $data = $request->get($class);

        if (null === $data || !is_array($data)) {
            return null;
        }

        $entity = $this->getEntity();
        if (null === $entity) {
            return $data;
        }

        return $entity->hydrate($data);
    }

    /**
     * @return ?KoncertoEntity
     */
    private function getEntity()
    {
        if (!array_key_exists('class', $this->options) || !is_string($this->options['class'])) {
            return null;
        }

        $class = $this->options['class'];
        $classFile = sprintf('_entity/%s.php', $class);
        if (!is_file($classFile)) {
            return null;
        }

        include_once $classFile;
        if (!class_exists($class)) {
            return null;
        }

        $entity = new $class();
        if (!is_a($entity, 'KoncertoEntity')) {
            return null;
        }

        return $entity;
    }
}


// phpcs:disable PSR1.Classes.ClassDeclaration

/**
 * KoncertoLive class to implement a JavaScript bridge
 * based on Impulsus actions and frames
 */
class KoncertoLive extends KoncertoController
{
    public function __construct()
    {
        session_start();
        if (!array_key_exists('csrf', $_SESSION) || empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = sha1(uniqid((string)mt_rand(), true) . microtime(true) . getmypid());
        }
        $request = new KoncertoRequest();
        $request->set('_csrf', $_SESSION['csrf']);
        $this->live();
    }

    /**
     * Get/set data from controller's live properties to Impulsus and back
     *
     * @internal {"route": {"name": "/_live"}}
     * @return KoncertoResponse
     */
    public function live()
    {
        if (null === $this->getRoute()) {
            throw new Exception(sprintf("Live controller [%s] requires a main route", get_class($this)));
        }

        $request = new KoncertoRequest();
        $csrf = $request->get('_csrf');
        if (null === $csrf || !is_string($csrf)) {
            throw new Exception('Missing required argument _csrf');
        }
        if (!array_key_exists('csrf', $_SESSION) || !$this->validateCsrf($_SESSION['csrf'], $csrf)) {
            throw new Exception('Invalid csrf token');
        }

        $props = $this->getLiveProps();

        $obj = array();
        foreach ($props as $propName => $prop) {
            if (is_array($prop) && array_key_exists('writable', $prop) && true === $prop['writable']) {
                $update = $request->get($propName);
                if (null !== $update) {
                    $this->$propName = $update;
                }
            }
            $obj[$propName] = $this->$propName;
        }

        return $this->json($obj, array('pretty' => true));
    }

    /**
     * @inheritdoc
     */
    public function render($template, $context = array(), $headers = array())
    {
        $response = parent::render($template, $context, $headers);

        $content = $response->getContent();

        $props = json_encode($this->getLiveProps());

        if (!array_key_exists('csrf', $_SESSION) || empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = sha1(uniqid((string)mt_rand(), true) . microtime(true) . getmypid());
        }
        $csrf = $_SESSION['csrf'];

        $controller = <<<JS
                    KoncertoImpulsus.controllers['live'] = function(controller) {
                        function liveProps() {
                            return {$props};
                        }
                        function liveUpdate(element) {
                            var update = '';
                            var props = liveProps();
                            for (var propName in props) {
                                var prop = props[propName];
                                if (prop.writable) {
                                    var target = element.targets['$' + propName];
                                    var value = target.innerText;
                                    var tagName = new String(target.tagName).toLowerCase();
                                    if ('input' === tagName) {
                                        value = target.value;
                                        if ('checkbox' === target.type) {
                                            value = target.checked ? target.value : '';
                                        }
                                    }
                                    update += '&' + encodeURIComponent(propName) + '=' + encodeURIComponent(value);
                                }
                            }

                            return update;
                        }
                        controller.on('$' + 'render',  function(controller) {
                            var csrf = 'csrf=' + controller.element.dataset.csrf;
                            KoncertoImpulsus.fetch('_live?' + csrf + liveUpdate(controller), false, function(response) {
                                var json = JSON.parse(response.responseText);
                                var props = liveProps();
                                for (var propName in props) {
                                    var prop = props[propName];
                                    if (propName in json) {
                                        var target = controller.targets['$' + propName];
                                        var tagName = new String(target.tagName).toLowerCase();
                                        if ('input' === tagName) {
                                            if ('checkbox' === target.type) {
                                                target.checked = target.value === json[propName];
                                                return;
                                            }
                                            target.value = json[propName];
                                            return;
                                        }
                                        if ('select' === tagName) {
                                            for (var i = 0; i < target.options.length; i++) {
                                                if (json[prop] === target.options[i].value) {
                                                    target.options.selectedIndex = i;
                                                    break;
                                                }
                                            }
                                            return;
                                        }
                                        target.innerText = json[propName];
                                    }
                                }
                            });
                        });
                    }

                    window.addEventListener('load', function() {
                        setTimeout(function() {
                            document.querySelector(':root').setAttribute('data-controller', 'live');
                            document.querySelector(':root').setAttribute('data-csrf', '{$csrf}');
                            document.querySelectorAll('[data-model]').forEach(function(model) {
                                model.setAttribute('data-target', '$' + model.getAttribute('data-model'));
                            });
                            var props = {$props};
                            for (var propName in props) {
                                var prop = props[propName];
                                console.debug(propName, prop);
                            }
                        }, 100);
                    });
JS;

        $impulsusLocations = array(
            'impulsus.js',
            'src/KoncertoImpulsus.js',
            'koncerto-impulsus/src/KoncertoImpulsus.js'
        );

        $impulsusValidLocations = array_filter($impulsusLocations, function ($impulsusLocation) {
            return is_file(dirname(__FILE__) . '/' . $impulsusLocation);
        });

        $impulsus = array_shift($impulsusValidLocations);

        if (null === $impulsus) {
            $impulsus = 'https://koncerto-framework.github.io/koncerto-playground/impulsus.js';
        }

        $content = str_replace('</head>', <<<HTML
                <script data-reload src="{$impulsus}"
                onload="if (window.reloadScript) reloadScript('#live-controller-script')"></script>
                <script id="live-controller-script" type="text/javascript">
                    {$controller}
                </script>
            </head>
HTML, $content);

        return $response->setContent($content);
    }

    /**
     * Get live props from class internal comments
     *
     * @return array<string, mixed>
     */
    private function getLiveProps()
    {
        $className = get_called_class();
        $props = Koncerto::cache('live', $className);
        if (is_array($props)) {
            return $props;
        }

        $props = array();

        $class = new ReflectionClass(get_called_class());
        $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($properties as $property) {
            $internal = Koncerto::getInternal($property->getDocComment());
            if (array_key_exists('live', $internal) && is_array($internal['live'])) {
                if (array_key_exists('prop', $internal['live'])) {
                    $props[$property->getName()] = $internal['live']['prop'];
                }
            }
        }

        Koncerto::cache('live', null, array($className => $props));

        return $props;
    }

    /**
     * Validate csrf token
     *
     * @param string $csrf
     * @param string $csrfToValidate
     * @return bool
     */
    private function validateCsrf($csrf, $csrfToValidate)
    {
        $length = strlen($csrf);
        if ($length !== strlen($csrfToValidate)) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < $length; $i++) {
            $result |= (ord($csrf[$i]) ^ ord($csrfToValidate[$i]));
        }

        return 0 === $result;
    }
}


// phpcs:disable PSR1.Classes.ClassDeclaration

/**
 * Helper class to parse request
 */
class KoncertoRequest
{
    /**
     * @return string
     */
    public function getPathInfo()
    {
        if (array_key_exists('PATH_INFO', $_SERVER)) {
            return $_SERVER['PATH_INFO'];
        }

        if (array_key_exists('REQUEST_URI', $_SERVER)) {
            return $_SERVER['REQUEST_URI'];
        }

        if ('true' === Koncerto::getConfig('routing.useHash')) {
            return (string)Koncerto::getConfig('request.pathInfo');
        }

        return '/';
    }

    /**
     * @param  string $argName
     * @return bool|float|int|string|null|array<array-key, bool|float|int|string|null>
     */
    public function get($argName)
    {
        if ('true' === Koncerto::getConfig('routing.useHash') && null !== Koncerto::getConfig('request.queryString')) {
            $queryString = array();
            parse_str(Koncerto::getConfig('request.queryString'), $queryString);
            $_REQUEST = $queryString;
        }

        if (!array_key_exists($argName, $_REQUEST)) {
            return null;
        }

        $value = $_REQUEST[$argName];
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if (!is_string($v) && !is_numeric($v) && !is_bool($v)) {
                    $v = null;
                }
                $value[$k] = (string)$v;
            }
        } else {
            if (!is_string($value) && !is_numeric($value) && !is_bool($value)) {
                $value = null;
            }
        }

        return $value;
    }

    /**
     * @param  string $argName
     * @param  mixed  $argValue
     * @return void
     */
    public function set($argName, $argValue)
    {
        $_REQUEST[$argName] = $argValue;
    }
}


// phpcs:disable PSR1.Classes.ClassDeclaration

/**
 * Helper class to prepare response
 */
class KoncertoResponse
{
    /**
     * @var array<string, string>
     */
    private $headers = array();

    /**
     * @var ?string
     */
    private $content = null;

    /**
     * @param  string  $headerName
     * @param  ?string $headerValue
     * @return KoncertoResponse
     */
    public function setHeader($headerName, $headerValue = null)
    {
        $headerName = strtolower(($headerName));

        if (null === $headerValue && array_key_exists($headerName, $this->headers)) {
            unset($this->headers[$headerName]);

            return $this;
        }

        $this->headers[$headerName] = (string)$headerValue;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param  ?string $content
     * @return KoncertoResponse
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return (string)$this->content;
    }
}


// phpcs:disable PSR1.Classes.ClassDeclaration

/**
 * Routing class
 * Koncerto matches KoncertoController classes from _controller folder
 * using internal {"route":{name:"/"}} annotation
 */
class KoncertoRouter
{
    /**
     * @var array<string, string>
     */
    private $routes = array();

    /**
     * Returns KoncertoController::action for the specified url (pathInfo)
     *
     * @param  string $url
     * @return ?string
     */
    public function match($url)
    {
        $this->getRoutes($url);

        if (array_key_exists($url, $this->routes)) {
            return $this->routes[$url];
        }

        return null;
    }

    /**
     * @param string $url
     * @return void
     */
    private function getRoutes($url)
    {
        $routes = Koncerto::cache('routes', null, $this->routes, '_controller');
        $this->routes = is_array($routes) ? array_filter(array_map(function ($route) {
            return is_string($route) ? $route : null;
        }, $routes)) : array();

        if (array_key_exists($url, $this->routes)) {
            return;
        }

        $d = '_controller/';
        if (!is_dir($d)) {
            mkdir($d);
        }

        $dir = opendir($d);
        if (false === $dir) {
            return;
        }

        while ($f = readdir($dir)) {
            if (is_file($d . $f) && '.php' === strrchr($f, '.')) {
                include_once $d . $f;
                $className = str_replace('.php', '', $f);
                if (class_exists($className)) {
                    if (is_subclass_of($className, 'KoncertoController')) {
                        $this->routes = array_merge(
                            $this->routes,
                            $this->getControllerRoutes($className)
                        );
                    }
                }
            }
        }

        Koncerto::cache('routes', null, $this->routes);
    }

    /**
     * @param  class-string $className
     * @return array<string, string>
     */
    private function getControllerRoutes($className)
    {
        $ref = new ReflectionClass($className);
        $mainRoute = (new KoncertoController())->getRoute($className);
        /**
          * @var array<string, string>
          */
        $routes = array();
        $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $routeName = $this->getControllerRoute($method->getDocComment());
            if (null === $routeName) {
                continue;
            }
            if (empty($routeName)) {
                $routeName = '/';
            }
            if (null !== $mainRoute && '/' === substr($mainRoute, 0, 1)) {
                $routeName = $mainRoute . $routeName;
            }

            $routes[$routeName] = sprintf(
                '%s::%s',
                $className,
                $method->getName()
            );
        }

        return $routes;
    }

    /**
     * @param  string|false $comment
     * @return ?string
     */
    public function getControllerRoute($comment)
    {
        $internal = Koncerto::getInternal($comment);

        if (!array_key_exists('route', $internal)) {
            return null;
        }

        if (!array_key_exists('name', $internal['route'])) {
            return null;
        }

        return $internal['route']['name'];
    }
}
