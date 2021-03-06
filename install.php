<?php
/* =-=-=-= Copyright © 2018 eMarket =-=-=-=  
  |    GNU GENERAL PUBLIC LICENSE v.3.0    |
  |  https://github.com/musicman3/eMarket  |
  =-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-= */

/* ++++++++++++++++++++++++++++++++++++++++ */
$mode = 'release'; // Mode release/master
$repo_init = 'musicman3/eMarket'; // GitHub name & repo
/* ++++++++++++++++++++++++++++++++++++++++ */

// php.ini set
ini_set('memory_limit', -1);
ini_set('max_execution_time', 0);
// Init
init($repo_init, $mode);

/**
 * Init
 * 
 * @param string $repo_init GitHub repo data
 * @param string $mode Mode
 */
function init($repo_init, $mode) {
    if (version_compare(PHP_VERSION, '8.0.0') < 0) {
        echo 'Attention. Your PHP version < 8.0. Please use version >= 8.0';
        exit;
    }

    // Repo name
    $repo = explode('/', $repo_init)[1];

    if (inGET('step') == '1') {
        $download = gitHubData($repo_init);
        if ($download !== FALSE) {
            downloadArchive($repo_init, $download, $mode);
        } else {
            echo json_encode(['Error', 'No data received from GitHub. Please refresh the page to repeat the installation procedure.']);
            exit;
        }
    }
    if (inGET('step') == '2') {
        UnzipArchive(inGET('param'), $repo);
    }
    if (inGET('step') == '3') {
        copyingFiles($repo);
    }
    if (inGET('step') == '4') {
        downloadComposer();
    }
    if (inGET('step') == '5') {
        composerInstall();
    }
}

/**
 * GET validation
 *
 * @param string $input Input data
 * @return mixed
 */
function inGET($input) {
    if (filter_input(INPUT_GET, $input, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FORCE_ARRAY) == TRUE) {
        if (isset($_GET[$input])) {
            return $_GET[$input];
        }
    }
    return FALSE;
}

/**
 * Download GitHub archive
 * 
 * @param string $repo_init GitHub repo data
 * @param string $download file name
 * @param string $mode Mode
 */
function downloadArchive($repo_init, $download, $mode) {
    $download_path = 'heads/master';
    if ($mode == 'release') {
        $download_path = 'tags/' . $download;
    }
    $file = 'https://github.com/' . $repo_init . '/archive/refs/' . $download_path . '.zip';
    $file_name = basename($file);
    file_put_contents(getenv('DOCUMENT_ROOT') . '/' . $file_name, file_get_contents($file));

    echo json_encode(['Install', 'Unzipping archive', '2', $file_name]);
    exit;
}

/**
 * Unzip GitHub archive
 *
 * @param string $file_name GutHub archive name
 * @param string $repo GitHub repo name
 */
function UnzipArchive($file_name, $repo) {
    $zip = new ZipArchive;
    $res = $zip->open(getenv('DOCUMENT_ROOT') . '/' . $file_name);
    if ($res === TRUE) {
        $zip->extractTo('.');
        $zip->close();
    } else {
        echo json_encode(['Error', 'An error has occurred. Please check the permissions for the root directory.']);
        exit;
    }

    filesRemoving($file_name);

    echo json_encode(['Install', 'Copying ' . $repo . ' files', '3', '0']);
    exit;
}

/**
 * Copying GutHub files
 *
 * @param string $repo GitHub repo name
 */
function copyingFiles($repo) {
    $source_dir = glob($repo . '*')[0];
    $copying_dir = $source_dir . '/src/' . $repo;
    $dest_dir = getenv('DOCUMENT_ROOT');
    if (!file_exists($dest_dir)) {
        mkdir($dest_dir, 0755, true);
    }
    $dir_iterator = new RecursiveDirectoryIterator($copying_dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $object) {
        $dest_path = $dest_dir . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        ($object->isDir()) ? mkdir($dest_path) : copy($object, $dest_path);
    }

    filesRemoving($source_dir);

    echo json_encode(['Install', 'Downloading composer.phar', '4', '0']);
    exit;
}

/**
 * Download composer.phar
 *
 */
function downloadComposer() {
    $file_composer = 'https://getcomposer.org/download/latest-stable/composer.phar';
    $file_name_composer = basename($file_composer);
    file_put_contents(getenv('DOCUMENT_ROOT') . '/' . $file_name_composer, file_get_contents($file_composer));

    echo json_encode(['Install', 'Installing vendor packages', '5', '0']);
    exit;
}

/**
 * Composer install
 *
 */
function composerInstall() {
    $root = realpath(getenv('DOCUMENT_ROOT'));
    $vendor_dir = $root . '/temp/vendor';
    $composerPhar = new Phar($root . '/composer.phar');
    $composerPhar->extractTo($vendor_dir);
    require_once($vendor_dir . '/vendor/autoload.php');
    putenv('COMPOSER_HOME=' . $vendor_dir . '/bin/composer');

    $params = [
        'command' => 'install'
    ];

    $input = new Symfony\Component\Console\Input\ArrayInput($params);
    $output = new Symfony\Component\Console\Output\BufferedOutput(
            Symfony\Component\Console\Output\OutputInterface::VERBOSITY_NORMAL
    );

    $application = new Composer\Console\Application();
    $application->setAutoExit(false);
    $application->run($input, $output);

    filesRemoving(getenv('DOCUMENT_ROOT') . '/composer.phar');
    filesRemoving(getenv('DOCUMENT_ROOT') . '/install.php');
    filesRemoving(getenv('DOCUMENT_ROOT') . '/temp');

    echo json_encode(['Done']);
    exit;
}

/**
 * Files removing
 *
 * @param string $path Path
 * @return bool
 */
function filesRemoving($path) {
    if (is_file($path)) {
        return unlink($path);
    }
    if (is_dir($path)) {
        foreach (scandir($path) as $p) {
            if (($p != '.') && ($p != '..')) {
                filesRemoving($path . DIRECTORY_SEPARATOR . $p);
            }
        }
        return rmdir($path);
    }
    return false;
}

/**
 * GitHub Data
 * 
 * @param string $repo_init GitHub repo data
 * @return string|bool GitHub latest release name
 */
function gitHubData($repo_init) {
    $connect = curl_init();
    curl_setopt($connect, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($connect, CURLOPT_HTTPHEADER, ['User-Agent: Installer']);
    curl_setopt($connect, CURLOPT_URL, 'https://api.github.com/repos/' . $repo_init . '/releases/latest');
    $response_string = curl_exec($connect);
    curl_close($connect);
    if (!empty($response_string)) {
        $response = json_decode($response_string, 1);
        if (isset($response['tag_name'])) {
            return $response['tag_name'];
        }
    } else {
        return FALSE;
    }
}
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
    <head>
        <link href = "https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel = "stylesheet" integrity = "sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin = "anonymous">
        <script src = "https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity = "sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin = "anonymous"></script>
        <script>
            /**
             * Ajax get
             *
             * @param url {String} (URL)
             */
            function getUpdate(url) {
                let xhr = new XMLHttpRequest();
                xhr.open('GET', url, false);
                xhr.send();
                if (xhr.status === 200) {
                    success(xhr);
                }
            }
            /**
             * Success
             *
             * @param xhr {Object} (xhr object)
             */
            function success(xhr) {
                var data = xhr.response;
                var parse = JSON.parse(data);
                if (parse[0] === 'Install' && Number(parse[2]) < 6) {
                    document.querySelector('#parts').insertAdjacentHTML('beforeend', '<div><span class="badge bg-success">' + parse[1] + '</span>&nbsp;</div>');
                    document.querySelector('#step').innerHTML = 'Step ' + parse[2] + ' of 5';
                    getUpdate(window.location.href + '?step=' + parse[2] + '&param=' + parse[3]);
                }
                if (parse[0] === 'Error') {
                    document.querySelector('#part_I').insertAdjacentHTML('beforeend', '<div><span class="badge bg-dark">' + parse[1] + '</span>&nbsp;</div>');
                }

                if (parse[0] === 'Done') {
                    window.location.href = 'controller/install/';
                }
            }
        </script>
    </head>
    <body>
        <div class="card text-center">
            <div class="card-header text-dark bg-warning">Attention! The eMarket installation is being prepared. Please do not refresh the page.</div>
            <div id="parts" class="card-body">
                <div><span class="badge bg-danger">ACTIONS:</span>&nbsp;</div>
                <div><span class="badge bg-success">Downloading <?php echo explode('/', $repo_init)[1] ?> archive</span>&nbsp;</div>
            </div>
            <div class="card-footer bg-transparent"><div><span id="step" class="badge bg-danger">Step 1 of 5</span>&nbsp;</div></div>
        </div>
        <script>getUpdate(window.location.href + '?step=1');</script>
    </body>
</html>