function proxyPhp(url, callback) {
    var result = document.createElement('section');
    var id = new Date().getTime();
    result.setAttribute('id', 'result-' + id);
    result.setAttribute('style', 'display: none;');
    document.body.appendChild(result);
    var script = php('#result-' + id, url);
    script.setAttribute('id', 'php-' + id)
    document.body.appendChild(script);
    setTimeout(function() {
        var html = result.innerHTML;
        result.parentNode.removeChild(result);
        script.parentNode.removeChild(script);
        callback(html);
    }, 500);
}

function proxy(url) {
    return `https://api.codetabs.com/v1/proxy?quest=${encodeURIComponent(url)}`;
}

window.proxy = proxy;

function php(target, files, storage, proxy) {
    if ('undefined' !== typeof newUrl) {
        if (0 !== newUrl.indexOf('http')) {
            var tmpUrl = new URL(new String(location));
            tmpUrl.hash = newUrl;
            newUrl = new String(tmpUrl);
        }
    }
    var useHash = true;
    var url = new URL(new String('undefined' !== typeof newUrl ? newUrl : location));
    var useHash = true;
    var pathInfo = new String(url.pathname);
    var queryString = new String(url.search);
    if (useHash) {
        pathInfo = new String(url.hash).substring(1);
        if (-1 !== pathInfo.indexOf('?')) {
            queryString = pathInfo.substring(pathInfo.indexOf('?') + 1);
            pathInfo = pathInfo.substring(0, pathInfo.indexOf('?'));
        }
    }
    if ('' === pathInfo) {
        pathInfo = '/';
    }

    var local = {};
    var localPrefix = '';
    var localMapping = '';
    if ('_localStorage' in storage) {
        localPrefix = storage['_localStorage'];
        var params = new URLSearchParams(queryString);
        params.keys().forEach(function (key) {
            localPrefix = localPrefix.replace('#' + key + '#', params.get(key));
        });
        var parts = localPrefix.split('->');
        if (2 === parts.length) {
            localPrefix = parts[0];
            localMapping = parts[1];
        }
        for (var key in localStorage) {
            if (0 === key.indexOf(localPrefix)) {
                var orig = key;
                if ('' !== localMapping) {
                    key = localMapping + key.substring(localPrefix.length);
                }
                local[key] = localStorage.getItem(orig);
            }
        }
        delete storage['_localStorage'];
    }
    if ('' === localMapping) {
        localMapping = localPrefix;
    }

    var script = document.createElement('script');
    script.setAttribute('type', 'text/php');
    script.setAttribute('data-files', JSON.stringify(files));
    script.setAttribute('data-stdout', target);
    script.innerText = `<?php

        ob_start();

        $storage = json_decode(rawurldecode('${encodeURIComponent(JSON.stringify(storage))}'), true);

        foreach ($storage as $dir => $files) {
            if (!is_dir($dir)) {
                mkdir($dir);
            }
            foreach ($files as $file) {
                file_put_contents(
                    sprintf('%s/%s', $dir, $file),
                    file_get_contents(sprintf(
                        rawurldecode('${encodeURIComponent(proxy)}'),
                        $dir,
                        $file
                    ))
                );
            }
        }

        $localStorage = json_decode(rawurldecode("${encodeURIComponent(JSON.stringify(local))}"), true);

        $d = rawurldecode('${encodeURIComponent(localMapping)}');
        if ('' !== $d && '.' !== $d && !is_dir($d)) {
            mkdir($d);
        }
        if ('' !== $d) {
            foreach ($localStorage as $path => $content) {
                list ($dir, $subdir, $file) = explode('/', $path);
                if (!is_dir($d . $dir)) {
                    mkdir($d . $dir);
                }
                if (!is_dir($d . $dir . '/' . $subdir)) {
                    mkdir($d . $dir . '/' . $subdir);
                }
                file_put_contents($path, $content);
            }
        }

        ob_end_clean();

        require_once('/preload/koncerto.php');

        Koncerto::setConfig(array(
            'routing' => array('useHash' => '${useHash}'),
            'documentRoot' => '/preload/',
            'request' => array(
                'pathInfo' => '${pathInfo}',
                'queryString' => '${queryString}'
            )
        ));

        echo Koncerto::response();
    `;

    return script;
}

window.reloadScript = function(selector) {
    document.querySelector(selector);
    eval(selector.text);
}

var targetNode = document.querySelector(':root');
var config = { childList: true, subtree: true };
var callback = function(mutationList, observer) {
    Array.from(mutationList).forEach(function(mutation) {
        var scripts = mutation.target.querySelectorAll('script');
        scripts.forEach(function (script) {
            if ('' !== script.src && script.hasAttribute('data-reload')) {
                fetch(script.src).then(function (result) {
                    result.text().then(function (js) {
                        eval(js);
                        if (script.hasAttribute('onload')) {
                            eval(script.getAttribute('onload'));
                        }
                    })
                });
            }
            if ('' === script.src) {
                if (-1 !== script.text.indexOf('//' + ' require is provided by loader.min.js.')) {
                    setTimeout(function() {
                        observer.disconnect();
                        eval(script.text);
                    }, 1000);
                } else {
                    if (script.hasAttribute('data-reload')) {
                        observer.disconnect();
                        eval(script.text);
                    }
                }
            }
        });
    });
};

var observer = new MutationObserver(callback);
observer.observe(targetNode, config);

document.addEventListener('DOMContentLoaded', function() {
    KoncertoImpulsus.fetch('files.json', false, function(response) {
        var files = JSON.parse(response.responseText);
        files.forEach(function (file, index) {
            if (0 === file.url.indexOf('@proxy:')) {
                file.url = file.url.substring(7);
            }
            files[index] = file;
        });
        KoncertoImpulsus.fetch('storage.json', false, function(response) {
            var storage = JSON.parse(response.responseText);
            var proxy = window.proxy('');
            if ('_proxy' in storage) {
                proxy = storage['_proxy'];
                delete storage['_proxy'];
            }
            var script = php(':root', files, storage, proxy);
            var head = document.getElementsByTagName('head')[0];
            head.appendChild(script);
        });
    });
});
