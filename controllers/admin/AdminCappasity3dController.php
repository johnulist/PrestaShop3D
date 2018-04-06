<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Cappasity Inc <info@cappasity.com>
 * @copyright 2014-2018 Cappasity Inc.
 * @license   http://cappasity.us/eula_modules/  Cappasity EULA for Modules
 */

require dirname(__FILE__) . '/../../vendor/autoload.php';

/**
 * Class AdminCappasity3dController
 */
class AdminCappasity3dController extends ModuleAdminController
{
    /**
     * Request params
     */
    const REQUEST_PARAM_TOKEN = 'token';
    const REQUEST_PARAM_PAGE = 'page';
    const REQUEST_PARAM_QUERY = 'query';
    const REQUEST_PARAM_VERIFY_TOKEN = 'verifyToken';
    const REQUEST_PARAM_CHALLENGE = 'challenge';

    /**
     *
     */
    public function __construct()
    {
        parent::__construct();

        $client = new CappasityClient();
        $dbManager = new CappasityManagerDatabase(Db::getInstance(), _DB_PREFIX_, _MYSQL_ENGINE_);

        $this->accountManager = new CappasityManagerAccount($client, $this->module);
        $this->dbManager = $dbManager;
        $this->fileManager = new CappasityManagerFile($client, $dbManager);
        $this->playerManager = new CappasityManagerPlayer($this->module);
        $this->syncManager = new CappasityManagerSync($client, $dbManager, $this->module);

        if (Tools::getValue(self::REQUEST_PARAM_TOKEN, null) === null) {
            return $this->processSync();
        }
    }

    /**
     *
     */
    public function processSync()
    {
        error_reporting(0);
        ignore_user_abort(true);
        set_time_limit(0);

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                echo Tools::safeOutput($this->processChallenge());
                break;
            case 'POST':
                echo Tools::safeOutput($this->processProducts());
                break;
        }

        die();
    }

    /**
     * @return string
     */
    public function processChallenge()
    {
        $verifyToken =  Tools::getValue(self::REQUEST_PARAM_VERIFY_TOKEN, null);
        $challenge = Tools::getValue(self::REQUEST_PARAM_CHALLENGE, null);

        if ($verifyToken === null || $challenge === null) {
            return '';
        }

        if ($this->syncManager->hasTask($verifyToken)) {
            return $challenge;
        }

        return '';
    }

    /**
     * @return string
     */
    public function processProducts()
    {
        $input = Tools::file_get_contents('php://input');
        $verifyToken =  Tools::getValue(self::REQUEST_PARAM_VERIFY_TOKEN, null);

        if ($verifyToken === null) {
            return '';
        }

        if ($this->syncManager->hasTask($verifyToken) === false) {
            return '';
        }

        if (array_key_exists('HTTP_CONTENT_ENCODING', $_SERVER) === true
            && $_SERVER['HTTP_CONTENT_ENCODING'] === 'gzip'
        ) {
            $input = gzdecode($input);
        }

        if ($input === false) {
            return '';
        }

        $products = Tools::jsonDecode($input, true);

        if ($products === null) {
            return '';
        }

        foreach ($products as $product) {
            $this->fileManager->update($product['id'], $product['uploadId']);
            usleep(500);
        }

        $this->syncManager->removeTask($verifyToken);

        return count($products);
    }

    /**
     * @return string
     */
    public function initContent()
    {
        $token = $this->accountManager->getToken();
        $alias = $this->accountManager->getAlias();

        $page = (int)Tools::getValue(self::REQUEST_PARAM_PAGE, 1);
        $query = Tools::getValue(self::REQUEST_PARAM_QUERY, '');

        try {
            $filesCollection = $this->fileManager->files($token, $query, $page, 12);
        } catch (Exception $e) {
            return $this->module->displayError(
                $this->module->l('Please renew your account settings')
            );
        }

        $this->context->smarty->assign(
            array(
                'action' => $this->context->link->getAdminLink('AdminCappasity3d', true),
                'files' => CappasityModelFile::getCollection(
                    $filesCollection['data'],
                    $this->playerManager->getSettings()
                ),
                'pagination' => $filesCollection['meta'],
                'alias' => $alias,
                'query' => $query,
            )
        );

        die($this->context->smarty->fetch($this->getTemplatePath() . 'list.tpl'));
    }
}
