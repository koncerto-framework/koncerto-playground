<?php

/**
 * Koncerto Framework
 * This is Koncerto Framework main class
 */
class Koncerto
{
    static $config = array();

    /**
     * Use Koncerto from source with autoload
     * @return void
     */
    public static function autoload() {
        spl_autoload_register('Koncerto::loadClass');
    }

    /**
     * Load class from source when using Koncerto with autoload
     * @param string $className
     * @return void
     */
    public static function loadClass($className) {
        if ('clsTinyButStrong' === $className && !class_exists('clsTinyButStrong')) {
            self::loadTBS();
            return;
        }

        if (!class_exists($className)) {
            require_once(dirname(__FILE__) . '/' . $className . '.php');
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return void
     */
    public static function setConfig($config) {
        Koncerto::$config = $config;
    }

    /**
     * @param string $entry
     * @return ?string
     */
    public static function getConfig($entry) {
        $config = Koncerto::$config;

        $path = explode('.', $entry);

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

        return $config;
    }

    /**
     * Static function to return response from Koncerto Framework
     * @return string
     */
    public static function response() {
        $request = new KoncertoRequest();
        $router = new KoncertoRouter();
        $match = $router->match($request->getPathInfo());
        if (null === $match) {
            throw new Exception(sprintf('No match for route %s', $request->getPathInfo()));
        }
        list($controller, $action) = explode('::', $match);
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
    private static function loadTBS() {
        $tbsLocations = array(
            dirname(__FILE__) . '/tbs_class.php',
            dirname(__FILE__) . '/../tbs_class.php',
            dirname(__FILE__) . '/tinybutstrong/tbs_class.php',
            dirname(__FILE__) . '/../tinybutstrong/tbs_class.php'
        );

        foreach ($tbsLocations as $tbsLocation) {
            if (is_file($tbsLocation)) {
                require_once($tbsLocation);

                return;
            }
        }
    }
}


/**
 *  Base (and Helper) class for Koncerto controllers
 */
class KoncertoController
{
    /**
     * @param string $template
     * @param array<string, mixed> $context
     * @param array<string, string> $headers
     * @return KoncertResponse
     */
    public function render($template, $context = array(), $headers = array()) {
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
            if (is_a($value, 'KoncertoForm')) {
                /** @var KoncertoForm */
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

        return $response->setContent($tbs->Show(false));
    }

    /**
     * @param array<array-key, mixed> $data
     * @return KoncertResponse
     */
    public function json($data) {
        return (new KoncertoResponse())
            ->setHeader('Content-type', 'application/json')
            ->setContent(json_encode($data));
    }
}


/**
 * Helper class for ORM
 */
class KoncertoEntity
{
    /**
     * @param array<string, mixed> $data
     * @return KoncertoEntity
     */
    public function hydrate($data) {
        $id = $this->getId();

        $ref = new ReflectionClass($this);
        $props = $ref->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($props as $prop) {
            $propName = $prop->getName();
            $emptyKey = null !== $id && array_key_exists($id, $data) && empty($data[$propName]);
            if (!$emptyKey && array_key_exists($propName, $data)) {
                // @todo - convert type
                $this->$propName = $data[$propName];
            }
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize() {
        $obj = array();

        $ref = new ReflectionClass($this);
        $props = $ref->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($props as $prop) {
            $propName = $prop->getName();
            $obj[$propName] = $this->$propName;
        }

        return $obj;
    }

    public function persist() {
        $pdo = new PDO(Koncerto::getConfig('entityManager.default'));

        $data = $this->serialize();
        $fields = array_keys($data);
        $placeholders = array_map(function ($field) { return ':' . $field; }, $fields);

        $entityName = strtolower(get_class($this));

        $id = $this->getId();
        if (null === $id) {
            return;
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
            $updates = array_map(function ($field, $placeholder) {
                return sprintf('%s = %s', $field, $placeholder);
            }, $fields, $placeholders);

            $query = $pdo->prepare(
                sprintf(
                    'UPDATE %s SET %s WHERE %s = %s',
                    $entityName,
                    implode(',', $updates),
                    $id,
                    $data[$id]
                )
            );
        }

        $query->execute($data);

        $data = array();
        if (!array_key_exists($id, $data) || empty($data[$id])) {
            $data = array($id => $pdo->lastInsertId());
        }

        return $this->hydrate($data);
    }

    /**
     * Get ID column from @internal comments
     * @return ?string
     */
    private function getId() {
        $ref = new ReflectionClass($this);
        $props = $ref->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($props as $prop) {
            $comment = $prop->getDocComment();
            if (false === $comment) {
                $comment = '';
            }
            $lines = explode("\n", $comment);
            foreach ($lines as $line) {
                $line = trim($line);
                $line = trim(preg_replace('/^\*[ ]*/', '', $line));
                if (1 === sscanf($line, '@internal %s', $json)) {
                    $internal = (array)json_decode($json, true);
                    if (!array_key_exists('key', $internal)) {
                        return null;
                    }

                    return $prop->getName();
                }
            }
        }
    }
}


/**
 * Helper class to generate form fields based on
 * Template engine and _form.tbs.html template
 */
class KoncertoField
{
    /** @var ?KoncertoForm */
    private $form = null;
    /** @var ?string */
    private $name = null;
    /** @var string */
    private $type = 'text';
    /** @var ?string */
    private $label = null;
    /** @var array<array-key, string> */
    private $options = array();

    /**
     * @param KoncertoForm $form
     * @return KoncertoField
     */
    public function setForm($form) {
        $this->form = $form;

        return $this;
    }

    /**
     * @return KoncertoForm
     */
    public function getForm() {
        return $this->form;
    }

    /**
     * @param ?string $key
     * @return mixed
     */
    public function getData($key = null) {
        $data = $this->form->getData();
        if (null === $data) {
            return null;
        }

        if (is_a($data, 'KoncertoEntity')) {
            $data = $data->serialize();
        }

        if (!array_key_exists($this->name, $data)) {
            return null;
        }

        return $data[$this->name];
    }

    /**
     * @param string $type
     * @return KoncertoField
     */
    public function setName($name) {
        if ('name' === $name) {
            throw new Exception(sprintf('KoncertoField::setName(%s) - "%s" is a reserved keyword', $name, $name));
        }

        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $type
     * @return KoncertoField
     */
    public function setType($type) {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param string $label
     * @return KoncertoField
     */
    public function setLabel($label) {
        $this->label = $label;

        return $this;
    }

    /**
     * @return ?string
     */
    public function getLabel() {
        return $this->label;
    }

    public function getFormName() {
        return 'form';
    }

    /**
     * @param array<array-key, string> $options
     * @return KoncertoField
     */
    public function setOptions($options) {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<array-key, string>
     */
    public function getOptions() {
        return $this->options;
    }
}


/**
 * Helper class to generate and submit forms
 * Requires template engine and _form.tbs.html template
 */
class KoncertoForm
{
    /** @var KoncertoField[] */
    private $fields = array();

    /** @var array<string, mixed> */
    private $options = array();

    /**
     * @param KoncertoField $field
     * @return KoncertoForm
     */
    public function add($field) {
        array_push($this->fields, $field);
        $field->setForm($this);

        return $this;
    }

    /**
     * @return KoncertoField[]
     */
    public function getFields() {
        return $this->fields;
    }

    /**
     * @param string $optionName
     * @param ?mixed $optionValue
     * @return KoncertoForm
     */
    public function setOption($optionName, $optionValue = null) {
        $optionName = strtolower(($optionName));

        if (null === $optionValue && array_key_exists($optionName, $this->options)) {
            unset($this->options[$optionName]);

            return $this;
        }

        $this->options[$optionName] = $optionValue;

        if ('data' === $optionName) {
            $request = new KoncertoRequest();
            foreach ($optionValue as $key => $val) {
                $request->set($key, $val);
            }
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions() {
        return $this->options;
    }

    /**
     * @return string
     */
    public function getName() {
        $name = 'form';
        if (array_key_exists('name', $this->options)) {
            $name = $this->options['name'];
        }

        return $name;
    }

    /**
     * @return bool
     */
    public function isSubmitted() {
        $request = new KoncertoRequest();
        $class = 'form';
        if (array_key_exists('class', $this->options)) {
            $class = strtolower($this->options['class']);
        }
        $form = $request->get($class);

        return null !== $form && is_array($form);
    }

    /**
     * @return mixed
     */
    public function getData() {
        $request = new KoncertoRequest();
        $class = 'form';
        if (array_key_exists('class', $this->options)) {
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
    private function getEntity() {
        if (!array_key_exists('class', $this->options)) {
            return null;
        }

        $class = $this->options['class'];
        $classFile = sprintf('_entity/%s.php', $class);
        if (!is_file($classFile)) {
            return null;
        }

        include_once($classFile);
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


/**
 * Helper class to parse request
 */
class KoncertoRequest
{
    /**
     * @return string
     */
    public function getPathInfo() {
        if (array_key_exists('PATH_INFO', $_SERVER)) {
            return $_SERVER['PATH_INFO'];
        }

        return '/';
    }

    /**
     * @param string $argName
     * @return mixed
     */
    public function get($argName) {
        if (!array_key_exists($argName, $_REQUEST)) {
            return null;
        }

        return $_REQUEST[$argName];
    }

    /**
     * @param string $argName
     * @param mixed $argValue
     * @return void
     */
    public function set($argName, $argValue) {
        $_REQUEST[$argName] = $argValue;
    }
}


/**
 * Helper class to prepare response
 */
class KoncertoResponse
{
    /** @var array<string, string> */
    private $headers = array();

    /** @var ?string */
    private $content = null;

    /**
     * @param string $headerName
     * @param ?string $headerValue
     * @return KoncertoResponse
     */
    public function setHeader($headerName, $headerValue = null) {
        $headerName = strtolower(($headerName));

        if (null === $headerValue && array_key_exists($headerName, $this->headers)) {
            unset($this->headers[$headerName]);

            return $this;
        }

        $this->headers[$headerName] = $headerValue;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders() {
        return $this->headers;
    }

    /**
     * @param ?string $content
     * @return KoncertoResponse
     */
    public function setContent($content) {
        $this->content = $content;

        return $this;
    }

    /**
     * @retrun string
     */
    public function getContent() {
        return $this->content;
    }
}


/**
 * Routing class
 * Koncerto matches KoncertoController classes from _controller folder
 * using @internal {"route":{name:"/"}} annotation
 */
class KoncertoRouter
{
    /** @var array<string, string> */
    private $routes = array();

    /**
     * Returns KoncertoController::action for the specified url (pathInfo)
     * @param string url
     * @return ?string
     */
    public function match($url) {
        $this->getRoutes($url);

        if (array_key_exists($url, $this->routes)) {
            return $this->routes[$url];
        }

        return null;
    }

    /**
     * @param $url
     * @return void
     */
    private function getRoutes($url) {
        if (0 === count($this->routes) && is_file('_cache/routes.json')) {
            $this->routes = (array)json_decode('_cache/routes.json', true);
        }

        if (array_key_exists($url, $this->routes)) {
            return;
        }

        $d = '_controller/';
        $dir = opendir($d);
        while ($f = readdir($dir)) {
            if (is_file($d . $f) && '.php' === strrchr($f, '.')) {
                include_once($d . $f);
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

        file_put_contents('_cache/routes.json', json_encode($this->routes));
    }

    /**
     * @param string $className
     * @return array<string, string>
     */
    private function getControllerRoutes($className) {
        /** @var array<string, string> */
        $routes = array();
        $methods = (new ReflectionClass($className))->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $comment = $method->getDocComment();
            if (false === $comment) {
                continue;
            }

            $routeName = $this->getControllerRoute($comment);
            if (null === $routeName) {
                continue;
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
     * @param string $comment
     * @return ?string
     */
    public function getControllerRoute($comment) {
        $lines = explode("\n", $comment);
        foreach ($lines as $line) {
            $line = trim($line);
            $line = trim(preg_replace('/^\*[ ]*/', '', $line));
            if (1 === sscanf($line, '@internal %s', $json)) {
                $internal = (array)json_decode($json, true);
                if (!array_key_exists('route', $internal)) {
                    return null;
                }
                if (!array_key_exists('name', $internal['route'])) {
                    return null;
                }

                return $internal['route']['name'];
            }
        }

        return null;
    }
}
