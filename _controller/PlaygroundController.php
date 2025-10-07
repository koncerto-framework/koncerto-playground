<?php

class PlaygroundController extends KoncertoController
{
    /**
     * @internal { "route": {"name": "/"} }
     * @return KoncertoReponse
     */
    public function index() {
        $files = array();

        $d = 'project/';
        if (!is_dir($d)) {
            mkdir($d);
        }

        $dir = opendir($d);
        while ($f = readdir($dir)) {
            if (is_dir($d . $f) && '_' === substr($f, 0, 1)) {
                $files[$f] = array_filter(scandir($d . $f), function ($file) {
                    return '.' !== substr($file, 0, 1);
                });
            }
        }
        return $this->render('_templates/index.tbs.html', array(
            'title' => 'Welcome',
            'files' => $files,
            'playgroundScript' => file_get_contents('_templates/playground.js')
        ));
    }
}
