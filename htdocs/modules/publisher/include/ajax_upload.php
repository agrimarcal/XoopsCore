<?php
// $Id$
//  ------------------------------------------------------------------------ //
//                XOOPS - PHP Content Management System                      //
//                    Copyright (c) 2000 XOOPS.org                           //
//                       <https://www.xoops.org>                             //
//  ------------------------------------------------------------------------ //
//  This program is free software; you can redistribute it and/or modify     //
//  it under the terms of the GNU General Public License as published by     //
//  the Free Software Foundation; either version 2 of the License, or        //
//  (at your option) any later version.                                      //
//                                                                           //
//  You may not change or alter any portion of this comment or credits       //
//  of supporting developers from this source code or any supporting         //
//  source code which is considered copyrighted (c) material of the          //
//  original comment or credit authors.                                      //
//                                                                           //
//  This program is distributed in the hope that it will be useful,          //
//  but WITHOUT ANY WARRANTY; without even the implied warranty of           //
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            //
//  GNU General Public License for more details.                             //
//                                                                           //
//  You should have received a copy of the GNU General Public License        //
//  along with this program; if not, write to the Free Software              //
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA //
//  ------------------------------------------------------------------------ //

use Xoops\Core\FixedGroups;
use XoopsModules\Publisher;
use XoopsModules\Publisher\Helper;

include dirname(dirname(dirname(__DIR__))) . '/mainfile.php';
require_once __DIR__ . '/common.php';

$xoops = Xoops::getInstance();
$xoops->disableErrorReporting();
if (!$xoops->isActiveModule('images')) {
    $arr = ['error', '!!!'];
    echo json_encode($arr);
    exit();
}

$helper = Helper::getInstance();
$helper->loadLanguage('common');

$group = $xoops->getUserGroups();

$filename = basename($_FILES['publisher_upload_file']['name']);
$image_nicename = isset($_POST['image_nicename']) ? trim($_POST['image_nicename']) : '';
if ('' == $image_nicename || _CO_PUBLISHER_IMAGE_NICENAME == $image_nicename) {
    $image_nicename = $filename;
}

$imgcat_id = isset($_POST['imgcat_id']) ? (int)$_POST['imgcat_id'] : 0;

$imgcatHandler = Images::getInstance()->getHandlerCategories();
$imgcat = $imgcatHandler->get($imgcat_id);

$error = false;
if (!is_object($imgcat)) {
    $error = _CO_PUBLISHER_IMAGE_CAT_NONE;
} else {
    $imgcatpermHandler = $xoops->getHandlerGroupPermission();
    if ($xoops->isUser()) {
        if (!$imgcatpermHandler->checkRight('imgcat_write', $imgcat_id, $xoops->user->getGroups())) {
            $error = _CO_PUBLISHER_IMAGE_CAT_NONE;
        }
    } else {
        if (!$imgcatpermHandler->checkRight('imgcat_write', $imgcat_id, FixedGroups::ANONYMOUS)) {
            $error = _CO_PUBLISHER_IMAGE_CAT_NOPERM;
        }
    }
}

$image = null;
if (false === $error) {
    $uploader = new XoopsMediaUploader(XoopsBaseConfig::get('uploads-path') . '/images', [
        'image/gif',
        'image/jpeg',
        'image/pjpeg',
        'image/x-png',
        'image/png',
    ], $imgcat->getVar('imgcat_maxsize'), $imgcat->getVar('imgcat_maxwidth'), $imgcat->getVar('imgcat_maxheight'));
    $uploader->setPrefix('img');
    if ($uploader->fetchMedia('publisher_upload_file')) {
        if (!$uploader->upload()) {
            $error = implode('<br>', $uploader->getErrors(false));
        } else {
            $imageHandler = Images::getInstance()->getHandlerImages();
            $image = $imageHandler->create();
            $image->setVar('image_name', $uploader->getSavedFileName());
            $image->setVar('image_nicename', $image_nicename);
            $image->setVar('image_mimetype', $uploader->getMediaType());
            $image->setVar('image_created', time());
            $image->setVar('image_display', 1);
            $image->setVar('image_weight', 0);
            $image->setVar('imgcat_id', $imgcat_id);
            if ('db' === $imgcat->getVar('imgcat_storetype')) {
                $fp = @fopen($uploader->getSavedDestination(), 'rb');
                $fbinary = @fread($fp, filesize($uploader->getSavedDestination()));
                @fclose($fp);
                $image->setVar('image_body', $fbinary);
                @unlink($uploader->getSavedDestination());
            }
            if (!$imageHandler->insert($image)) {
                $error = sprintf(_CO_PUBLISHER_FAILSAVEIMG, $image->getVar('image_nicename'));
            }
        }
    } else {
        $error = sprintf(_CO_PUBLISHER_FAILSAVEIMG, $filename) . '<br>' . implode('<br>', $uploader->getErrors(false));
    }
}

if ($error) {
    $arr = ['error', Publisher\Utils::convertCharset($error)];
} else {
    $arr = [
        'success',
        $image->getVar('image_name'),
        Publisher\Utils::convertCharset($image->getVar('image_nicename')),
    ];
}

$echo = json_encode($arr);
echo $echo;
exit();
