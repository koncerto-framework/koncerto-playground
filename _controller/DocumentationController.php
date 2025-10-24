<?php

/**
 * @internal {"route":{"name":"/doc"}}
 */
class DocumentationController extends KoncertoLive
{
    /**
     * @internal {"route":{"name":"/"}}
     * @return KoncertoReponse
     */
    public function index() {
$page = (new KoncertoRequest())->get('page'); // Utiliser $this->request si centralisé
        if (null === $page) {
            $page = 'Introduction';
        }

        $markdownContent = file_get_contents('/preload/' . $page . '.md');

        $router = new KoncertoRouter();

        $processedContent = preg_replace_callback(
            '/\[(.*?)\]\((Koncerto.*?)\)/',
            function ($matches) use ($router) {
                $linkText = $matches[1];
                $linkUrl = $matches[2];
                if (false === strpos($linkUrl, '://')) {
                    $newUrl = $router->generate('/doc', array('page' => $linkUrl));

                    return '[' . $linkText . '](' . $newUrl . ')';
                }

                return $matches[0];
            },
            (string)$markdownContent
        );

        return $this->render('_templates/doc.tbs.html', array(
            'title' => 'Documentation',
            'md' => $processedContent
        ));
    }
}
