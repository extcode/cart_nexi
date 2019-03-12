<?php

namespace Extcode\CartNexi\Utility;

use Extcode\Cart\Domain\Repository\Order;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class PaymentProcess
{
    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var TypoScriptService
     */
    protected $typoScriptService;

    /**
     * @var Order\ItemRepository
     */
    protected $orderItemRepository;

    /**
     * @var Order\PaymentRepository
     */
    protected $orderPaymentRepository;

    /**
     * @var array
     */
    protected $settings = [];

    public function __construct()
    {
        $this->objectManager = GeneralUtility::makeInstance(
            ObjectManager::class
        );
        $this->persistenceManager = $this->objectManager->get(
            PersistenceManager::class
        );
        $this->typoScriptService = $this->objectManager->get(
            TypoScriptService::class
        );
        $this->orderItemRepository = $this->objectManager->get(
            Order\ItemRepository::class
        );
        $this->orderPaymentRepository = $this->objectManager->get(
            Order\PaymentRepository::class
        );

        $this->getTypoScript();
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response)
    {
        switch ($request->getMethod()) {
            case 'POST':
                $this->processPostRequest($request, $response);
                break;
            default:
                $response->withStatus(405, 'Method not allowed');
        }

        return $response;
    }

    protected function getTypoScript()
    {
        $pageId = (int)GeneralUtility::_GP('pageid');
        $GLOBALS['TSFE'] = GeneralUtility::makeInstance(
            \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::class,
            $GLOBALS['TYPO3_CONF_VARS'],
            $pageId,
            0,
            true
        );
        \TYPO3\CMS\Frontend\Utility\EidUtility::initLanguage();

        $GLOBALS['TSFE']->connectToDB();
        $GLOBALS['TSFE']->initFEuser();
        \TYPO3\CMS\Frontend\Utility\EidUtility::initTCA();

        $GLOBALS['TSFE']->initUserGroups();
        $GLOBALS['TSFE']->determineId();
        $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(
            \TYPO3\CMS\Frontend\Page\PageRepository::class
        );
        $GLOBALS['TSFE']->initTemplate();
        $GLOBALS['TSFE']->getConfigArray();

        $this->settings = $this->typoScriptService->convertTypoScriptArrayToPlainArray(
            $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_cartnexi.']
        );
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function processPostRequest(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response)
    {
        $macHash = $this->settings['redirectTypeNum'];

        $requiredParams = ['codTrans', 'esito', 'importo', 'divisa', 'data', 'orario', 'codAut', 'mac'];
        foreach ($requiredParams as $param) {
            if (!isset($_REQUEST[$param])) {
                echo 'Paramentro mancante ' . $param;
                header('500 Internal Server Error', true, 500);
                exit;
            }
        }

        $macCalculated = sha1(
            'codTrans=' . $_REQUEST['codTrans'] .
            'esito=' . $_REQUEST['esito'] .
            'importo=' . $_REQUEST['importo'] .
            'divisa=' . $_REQUEST['divisa'] .
            'data=' . $_REQUEST['data'] .
            'orario=' . $_REQUEST['orario'] .
            'codAut=' . $_REQUEST['codAut'] .
            $macHash
        );

        if ($macCalculated !== $_REQUEST['mac']) {
            echo 'Errore MAC: ' . $macCalculated . ' non corrisponde a ' . $_REQUEST['mac'];
            header('500 Internal Server Error', true, 500);
            exit;
        }

        /** @var \Extcode\Cart\Domain\Model\Order\Item $orderItem */
        $orderItem = $this->orderItemRepository->findOneByOrderNumber($_REQUEST['codTrans']);
        $orderPayment = $orderItem->getPayment();

        if ($_REQUEST['esito'] === 'OK') {
            $orderPayment->setStatus('paid');

            $this->orderPaymentRepository->update($orderPayment);
            $this->persistenceManager->persistAll();
            header('OK, pagamento avvenuto, preso riscontro', true, 200);
        } else {
            $orderPayment->setStatus('error');

            $this->orderPaymentRepository->update($orderPayment);
            $this->persistenceManager->persistAll();
            header('KO, pagamento non avvenuto, preso riscontro', true, 200);
        }
    }
}
