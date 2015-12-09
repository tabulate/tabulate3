<?php

namespace Tabulate\Controllers;

use Tabulate\Config;

class ErdController extends ControllerBase {

    /** @var array|string */
    private $tables;

    /** @var array|string */
    private $selectedTables;

    public function __construct() {
        parent::__construct();
        $db = new \Tabulate\DB\Database();
        $this->selectedTables = array();
        foreach ($db->get_tables() as $table) {
            $this->tables[] = $table;
            // If any tables are requested, only show them
            if (isset($_GET['tables']) && count($_GET['tables']) > 0) {
                if (in_array($table->getName(), $_GET['tables'])) {
                    $this->selectedTables[$table->getName()] = $table;
                }
            } else { // Otherwise, default to all linked tables
                $referenced = count($table->get_referencing_tables()) > 0;
                $referencing = count($table->get_referenced_tables()) > 0;
                if ($referenced || $referencing) {
                    $this->selectedTables[$table->getName()] = $table;
                }
            }
        }
    }

    public function index() {
        $template = new \Tabulate\Template('erd/display.twig');
        $template->title = 'ERD';
        $template->tables = $this->tables;
        $template->selectedTables = $this->selectedTables;
        if ($this->selectedTables) {
            $pngUrl = [];
            foreach ($this->selectedTables as $stab) {
                $pngUrl[] = 'tables[]=' . $stab->getName();
            }
            $template->pngUrl = 'erd.png?' . join('&', $pngUrl);
        }
        echo $template->render();
    }

    public function render() {

        // Generate the DOT source code, and write to a file.
        $dot = new \Tabulate\Template('erd/erd.twig');
        $dot->tables = $this->tables;
        $dot->selectedTables = $this->selectedTables;
        $dotCode = $dot->render();
        $tmpFilePath = Config::storageDirTmp('erd/' . uniqid());
        $dotFile = $tmpFilePath . '/erd.dot';
        $pngFile = $tmpFilePath . '/erd.png';
        file_put_contents($dotFile, $dotCode);

        // Generate the image.
        $cmd = Config::dotCommand() . ' -Tpng -o' . escapeshellarg($pngFile) . ' ' . escapeshellarg($dotFile);
        $ds = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $pipes = false;
        $proc = proc_open($cmd, $ds, $pipes, Config::storageDirTmp('erd'), array());
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($proc);
        if (!empty($err)) {
            throw new \Exception("Error generating graph image. $err");
        }

        // Send the image.
        header('Content-Type:image/png');
        echo file_get_contents($pngFile);

        // Clean up.
        \Tabulate\File::rmdir($tmpFilePath);
    }

}
