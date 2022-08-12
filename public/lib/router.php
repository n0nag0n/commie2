<?php
namespace n0nag0n\paste;

require_once __DIR__.'/../../lib/PasteManager.php';

$PasteManager = new PasteManager();

if(isset($_REQUEST['uid'])) {
    $_REQUEST['uid'] = preg_replace("#(*UTF8)[^A-Za-z0-9]#", '', $_REQUEST['uid']);
}

switch ($_REQUEST['do']) {
    case 'save':
        $json = $PasteManager->savePaste($_REQUEST['content'], $_REQUEST['name'], $_REQUEST['email']);
        break;
    case 'load':
        $json = $PasteManager->loadPaste($_REQUEST['uid']);
        break;
    case 'loadComments':
        $json = $PasteManager->loadComments($_REQUEST['uid']);
        break;
    case 'saveComment':
        $json = $PasteManager->saveComment($_REQUEST['uid'], (int) $_REQUEST['line'], $_REQUEST['comment'], $_REQUEST['user'], $_REQUEST['email']);
        break;
    default:
        $json = false;
}

header('Content-Type: application/json');
echo $json;

