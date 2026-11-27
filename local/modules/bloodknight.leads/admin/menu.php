<?php
if ($GLOBALS['USER']->IsAdmin()) {
    return [
        'parent_menu' => 'global_menu_services', 'section' => 'bloodknight_leads',
        'sort' => 500, 'text' => 'Заявки веб-форм', 'title' => 'Заявки веб-форм',
        'url' => 'bloodknight_leads.php?lang='.LANGUAGE_ID,
    ];
}
