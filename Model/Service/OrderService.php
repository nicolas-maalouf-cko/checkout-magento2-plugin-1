<?php

namespace CheckoutCom\Magento2\Model\Service;

use Magento\Quote\Model\Quote;
use Magento\Checkout\Helper\Data;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Api\AgreementsValidatorInterface;
use CheckoutCom\Magento2\Model\Ui\ConfigProvider;
use CheckoutCom\Magento2\Observer\DataAssignObserver;

class OrderService {

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var AgreementsValidatorInterface
     */
    private $agreementsValidator;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var Data
     */
    private $checkoutHelper;

    /**
     * OrderService constructor.
     * @param CartManagementInterface $cartManagement
     * @param AgreementsValidatorInterface $agreementsValidator
     * @param Session $customerSession
     * @param Data $checkoutHelper
     */
    public function __construct(
        CartManagementInterface $cartManagement,
        AgreementsValidatorInterface $agreementsValidator,
        Session $customerSession,
        Data $checkoutHelper
    ) {
        $this->cartManagement       = $cartManagement;
        $this->agreementsValidator  = $agreementsValidator;
        $this->customerSession      = $customerSession;
        $this->checkoutHelper       = $checkoutHelper;
    }

    /**
     * Runs the service.
     *
     * @param Quote $quote
     * @param $cardToken
     * @param array $agreement
     * @throws LocalizedException
     */
    public function execute(Quote $quote, $cardToken, array $agreement) {
        if (!$this->agreementsValidator->isValid($agreement)) {
            throw new LocalizedException(__('Please agree to all the terms and conditions before placing the order.'));
        }

        if ($this->getCheckoutMethod($quote) === Onepage::METHOD_GUEST) {
            $this->prepareGuestQuote($quote);
        }

        $payment = $quote->getPayment();
        $payment->setMethod(ConfigProvider::CODE);
        $payment->setAdditionalInformation(DataAssignObserver::CARD_TOKEN_ID, $cardToken);

        $this->disabledQuoteAddressValidation($quote);
        $quote->collectTotals();

        $this->cartManagement->placeOrder($quote->getId());
    }

    /**
     * Get checkout method.
     *
     * @param Quote $quote
     * @return string
     */
    private function getCheckoutMethod(Quote $quote) {
        if ($this->customerSession->isLoggedIn()) {
            return Onepage::METHOD_CUSTOMER;
        }

        if (!$quote->getCheckoutMethod()) {
            if ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }

        return $quote->getCheckoutMethod();
    }

    /**
     * Prepare quote for guest checkout order submit.
     *
     * @param Quote $quote
     * @return void
     */
    private function prepareGuestQuote(Quote $quote) {
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
    }

    /**
     * Disables the address validation for the given quote instance.
     *
     * @param Quote $quote
     */
    protected function disabledQuoteAddressValidation(Quote $quote) {
        $billingAddress = $quote->getBillingAddress();
        $billingAddress->setShouldIgnoreValidation(true);

        if (!$quote->getIsVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setShouldIgnoreValidation(true);

            if (!$billingAddress->getEmail()) {
                $billingAddress->setSameAsBilling(1);
            }
        }
    }

}
