<?php
defined('TYPO3_MODE') or die();

call_user_func(function () {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        'cart_nexi',
        'Configuration/TypoScript',
        'Shopping Cart - Nexi'
    );
});
