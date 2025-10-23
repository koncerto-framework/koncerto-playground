<?php

/**
 * @internal { "route": {"name": "/"} }
 */
class PlaygroundController extends KoncertoLive
{
    /**
     * @internal { "route": {"name": "/"} }
     * @return KoncertoReponse
     */
    public function index() {
        $files = array();

        $d = 'projects/';
        if (!is_dir($d)) {
            mkdir($d);
        }

        $files = array();

        $request = new KoncertoRequest();
        $project = $request->get('project');
        if (null !== $project) {
            $d .= $project . '/';
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
        }


        return $this->render('_templates/index.tbs.html', array(
            'title' => 'Welcome',
            'files' => $files
        ));
    }
}
