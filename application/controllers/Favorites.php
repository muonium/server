<?php
namespace application\controllers;
use \library\MVC as l;
use \application\models as m;

/*class Favorites extends l\Controller {

    function __construct() {
        parent::__construct([
            'mustBeLogged' => true,
            'mustBeValidated' => true
        ]);
    }

    function DefaultAction() {
        $mFiles = new m\Files($_SESSION['id']);
        $tabFavorites = $mFiles->getFavorites();
        $favorites = '';
        foreach($tabFavorites as $fav) {
            $favorites .= '<span class="file" id="f'.$fav['id'].'" data-folder="'.htmlentities($fav['folder_id']).'" data-title="'.htmlentities($fav['name']).'">
				'.htmlentities($fav['name']).' ['.showSize($fav['size']).'] - '.self::$txt->User->lastmod.' : '.date(self::$txt->Dates->date.' '.self::$txt->Dates->time, $fav['last_modification'])."</span>\n";
        }
        require_once(DIR_VIEW.'Favorites.php');
    }
}*/
