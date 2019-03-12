<?php

namespace Extcode\CartNexi\Utility;

use Extcode\Cart\Domain\Repository\CartRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Web\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class PaymentUtility
{
    const PAYMENT_API_SANDBOX = 'https://int-ecommerce.nexi.it/ecomm/ecomm/DispatcherServlet?';
    const PAYMENT_API_LIVE = 'https://ecommerce.nexi.it/ecomm/ecomm/DispatcherServlet?';

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var CartRepository
     */
    protected $cartRepository;

    /**
     * @var array
     */
    protected $conf = [];

    /**
     * @var array
     */
    protected $cartConf = [];

    /**
     * @var array
     */
    protected $paymentQuery = [];

    /**
     * Order Item
     *
     * @var \Extcode\Cart\Domain\Model\Order\Item
     */
    protected $orderItem = null;

    /**
     * Cart
     *
     * @var \Extcode\Cart\Domain\Model\Cart\Cart
     */
    protected $cart = null;

    /**
     * CartFHash
     *
     * @var string
     */
    protected $cartFHash = '';

    /**
     * CartSHash
     *
     * @var string
     */
    protected $cartSHash = '';

    /**
     * Intitialize
     */
    public function __construct()
    {
        $this->objectManager = GeneralUtility::makeInstance(
            ObjectManager::class
        );
        $this->persistenceManager = $this->objectManager->get(
            PersistenceManager::class
        );
        $this->configurationManager = $this->objectManager->get(
            ConfigurationManager::class
        );

        $this->conf = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'CartNexi'
        );

        $this->cartConf = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );
    }

    /**
     * implements handlePayment signal slot
     *
     * @param array $params
     *
     * @return array
     */
    public function handlePayment($params): array
    {
        $this->orderItem = $params['orderItem'];

        if ($this->orderItem->getPayment()->getProvider() === 'NEXI_EASY_PAYMENT') {
            $params['providerUsed'] = true;

            $this->cart = $params['cart'];

            $cart = $this->objectManager->get(
                \Extcode\Cart\Domain\Model\Cart::class
            );
            $cart->setOrderItem($this->orderItem);
            $cart->setCart($this->cart);
            $cart->setPid($this->cartConf['settings']['order']['pid']);

            $cartRepository = $this->objectManager->get(
                CartRepository::class
            );
            $cartRepository->add($cart);

            $identification = $this->orderItem->getOrderNumber();
            $currencyCode = $this->orderItem->getCurrencyCode();
            $amount = round($this->orderItem->getTotalGross() * 100);

            $mac = sha1('codTrans=' . $identification . 'divisa=' . $currencyCode . 'importo=' . $amount . $this->conf['macHash']);

            $requestParams = [
                'alias' => $this->conf['alias'],
                'importo' => $amount,
                'divisa' => $currencyCode,
                'codTrans' => $identification,
                'url' => $this->getUrl('success', $cart->getSHash()),
                'url_back' => $this->getUrl('cancel', $cart->getFHash()),
                'mac' => $mac,
                'urlpost' => $this->getNotifyUrl(),
            ];

            $paymentQueryString = http_build_query($requestParams);
            $redirectUrl = $this->getRequestUrl() . $paymentQueryString;

            header('Location: ' . $redirectUrl);
        }

        return [$params];
    }

    /**
     * @return string
     */
    protected function getRequestUrl(): string
    {
        if ($this->conf['sandbox']) {
            return self::PAYMENT_API_SANDBOX;
        }

        return self::PAYMENT_API_LIVE;
    }

    /**
     * @return string
     */
    protected function getNotifyUrl(): string
    {
        $arguments = [
            'eID' => 'nexi-payment-api',
        ];

        $uriBuilder = $this->getUriBuilder();

        return $uriBuilder->reset()
            ->setCreateAbsoluteUri(true)
            ->setUseCacheHash(false)
            ->setArguments($arguments)
            ->build();
    }

    /**
     * Builds a return URL to Cart order controller action
     *
     * @param string $action
     * @param string $hash
     *
     * @return string
     */
    protected function getUrl(string $action, string $hash) : string
    {
        $pid = $this->cartConf['settings']['cart']['pid'];

        $arguments = [
            'tx_cartnexi_cart' => [
                'controller' => 'Order\Payment',
                'order' => $this->orderItem->getUid(),
                'action' => $action,
                'hash' => $hash
            ]
        ];

        $uriBuilder = $this->getUriBuilder();

        return $uriBuilder->reset()
            ->setTargetPageUid($pid)
            ->setTargetPageType($this->conf['redirectTypeNum'])
            ->setCreateAbsoluteUri(true)
            ->setUseCacheHash(false)
            ->setArguments($arguments)
            ->build();
    }

    /**
     * @return UriBuilder
     */
    protected function getUriBuilder(): UriBuilder
    {
        $request = $this->objectManager->get(Request::class);
        $request->setRequestURI(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));
        $request->setBaseURI(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'));
        $uriBuilder = $this->objectManager->get(UriBuilder::class);
        $uriBuilder->setRequest($request);

        return $uriBuilder;
    }
}
