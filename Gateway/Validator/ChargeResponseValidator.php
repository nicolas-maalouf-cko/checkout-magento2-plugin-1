<?php

namespace CheckoutCom\Magento2\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use CheckoutCom\Magento2\Model\Validator\Rule;

class ChargeResponseValidator extends ResponseValidator {

    /**
     * Returns the array of the rules.
     *
     * @return Rule[]
     */
    protected function rules() {
        return [
            new Rule('Transaction ID', function(array $subject) {
                $response = SubjectReader::readResponse($subject);

                return array_key_exists('id', $response);
            }, __('Checkout.com response does not have transaction ID.') ),

            new Rule('Associated Track ID', function(array $subject) {
                $paymentDO  = SubjectReader::readPayment($subject);
                $response   = SubjectReader::readResponse($subject);

                return $paymentDO->getOrder()->getOrderIncrementId() === $response['trackId'];
            }, __('Checkout.com track ID is not the same.') ),
        ];
    }

}
