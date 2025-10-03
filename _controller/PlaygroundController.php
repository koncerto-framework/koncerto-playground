<?php

class PlaygroundController extends KoncertoController
{
    /**
     * @internal { "route": {"name": "/"} }
     * @return KoncertoReponse
     */
    public function index() {
        $files = array();
        $dir = opendir('.');
        while ($d = readdir($dir)) {
            if (is_dir($d) && '_' === substr($d, 0, 1)) {
                $files[$d] = array_filter(scandir($d), function ($f) {
                    return '.' !== substr($f, 0, 1);
                });
            }
        }
        return $this->render('_templates/index.tbs.html', array(
            'title' => 'Welcome',
            'files' => $files
        ));
    }
}
