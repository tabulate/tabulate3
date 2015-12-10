<?php

namespace Tabulate;

class Template
{

    /** @var string */
    protected $templateName;

    /** @var string */
    protected $template_string;

    /** @var string[] */
    protected $data;

    /** @var /Twig_Loader_Filesystem */
    protected $loader;

    /** @var string The name of the transient used to store notices. */
    protected $transient_notices;

    /**
     * Create a new template either with a file-based Twig template, or a Twig string.
     * @global type $wpdb
     * @param string|false $templateName
     * @param string|false $templateString
     */
    public function __construct($templateName = false, $templateString = false)
    {
        $this->templateName = $templateName;
        $this->template_string = $templateString;
        $this->transient_notices = 'notices';
        $notices = isset($_SESSION[$this->transient_notices]) ? $_SESSION[$this->transient_notices] : array();
        $this->data = array(
            'tabulate_version' => TABULATE_VERSION,
            'notices' => $notices,
            'baseurl' => Config::baseUrl(),
            'debug' => Config::debug(),
            'site_title' => Config::siteTitle(),
        );
        $this->loader = new \Twig_Loader_Filesystem([__DIR__ . '/../templates']);
    }

    public function setTemplateName($newName)
    {
        $this->templateName = $newName;
    }

    /**
     * Get the Filesystem template loader.
     * @return \Twig_Loader_Filesystem 
     */
    public function getLoader()
    {
        return $this->loader;
    }

    /**
     * Get a list of templates in a given directory, across all registered template paths.
     * @param string $directory
     */
    public function get_templates($directory)
    {
        $templates = array();
        foreach ($this->getLoader()->getPaths() as $path) {
            $dir = $path . '/' . ltrim($directory, '/');
            foreach (preg_grep('/^[^\.].*\.(twig|html)$/', scandir($dir)) as $file) {
                $templates[] = $directory . '/' . $file;
            }
        }
        return $templates;
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * Find out whether a given item of template data is set.
     *
     * @param string $name The property name.
     * @return boolean
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * Get an item from this template's data.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->data[$name];
    }

    /**
     * Add a notice. All notices are saved to a Transient, which is deleted when
     * the template is rendered but otherwise available to all subsequent
     * instances of the Template class.
     * @param string $type Either 'updated' or 'error'.
     * @param string $message The message to display.
     */
    public function add_notice($type, $message)
    {
        $this->data['notices'][] = array(
            'type' => $type,
            'message' => $message,
        );
        $_SESSION[$this->transient_notices] = $this->data['notices'];
    }

    /**
     * Render the template and return it.
     * @return void
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * Render the template and return the output.
     * @return string
     */
    public function render()
    {
        unset($_SESSION[$this->transient_notices]);
        $twig = new \Twig_Environment($this->loader);

        // Add titlecase filter.
        $titlecase_filter = new \Twig_SimpleFilter('titlecase', '\\WordPress\\Tabulate\\Text::titlecase');
        $twig->addFilter($titlecase_filter);

        // Add strtolower filter.
        $strtolower_filter = new \Twig_SimpleFilter('strtolower', function( $str ) {
            if (is_array($str)) {
                return array_map('strtolower', $str);
            } else {
                return strtolower($str);
            }
        });
        $twig->addFilter($strtolower_filter);

        // Enable debugging.
        if (Config::debug()) {
            $this->queries = DB\Database::getQueries();
            $twig->enableDebug();
            $twig->addExtension(new \Twig_Extension_Debug());
        }

        // Render the template.
        if (!empty($this->template_string)) {
            $template = $twig->createTemplate($this->template_string);
        } else {
            $template = $twig->loadTemplate($this->templateName);
        }
        return $template->render($this->data);
    }
}
