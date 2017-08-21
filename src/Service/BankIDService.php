<?php

namespace Dimafe6\BankID\Service;

use Dimafe6\BankID\Model\CollectResponse;
use Dimafe6\BankID\Model\OrderResponse;
use SoapClient;

/**
 * Class BankIDService
 *
 * @category PHP
 * @package  Dimafe6\BankID\Service
 * @author   Dmytro Feshchenko <dimafe2000@gmail.com>
 */
class BankIDService
{
    /**
     * Bank ID Sign method name
     */
    const METHOD_SIGN = 'Sign';

    /**
     * Bank ID Authenticate method name
     */
    const METHOD_AUTH = 'Authenticate';

    /**
     * Bank ID Collect method name
     */
    const METHOD_COLLECT = 'Collect';

    /**
     * @var SoapClient
     */
    private $client;

    /**
     * @var string
     */
    private $wsdlUrl;

    /**
     * @var string
     */
    private $soapOptions;

    /**
     * BankIDService constructor.
     * @param string $wsdlUrl Bank ID API url
     * @param array $options SoapClient options
     * @param bool $enableSsl Enable SSL
     */
    public function __construct($wsdlUrl, array $options, $enableSsl)
    {
        if (!$enableSsl) {
            $context = stream_context_create(array(
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ));

            $options['stream_context'] = $context;
        }

        $this->wsdlUrl = $wsdlUrl;
        $this->soapOptions = $options;
        $this->client = new SoapClient($this->wsdlUrl, $this->soapOptions);
    }

    /**
     * @param $personalNumber
     * @param $userVisibleData
     * @return OrderResponse
     * @throws \SoapFault
     */
    public function getSignResponse($personalNumber, $userVisibleData)
    {
        $userVisibleData = base64_encode($userVisibleData);
        $parameters = [
            'parameters' => [
                'personalNumber' => $personalNumber,
                'userVisibleData' => $userVisibleData,
            ],
        ];

        $response = $this->client->__soapCall(self::METHOD_SIGN, $parameters);

        $orderResponse = new OrderResponse();
        $orderResponse->orderRef = $response->orderRef;
        $orderResponse->autoStartToken = $response->autoStartToken;

        return $orderResponse;
    }

    /**
     * @param $personalNumber
     * @return OrderResponse|null
     */
    public function getAuthResponse($personalNumber)
    {
        $parameters = [
            'parameters' => [
                'personalNumber' => $personalNumber
            ],
        ];

        try {
            $response = $this->client->__soapCall(self::METHOD_AUTH, $parameters);

            if ($response->orderRef && $response->autoStartToken
            ) {
                $orderResponse = new OrderResponse();
                $orderResponse->orderRef = $response->orderRef;
                $orderResponse->autoStartToken = $response->autoStartToken;

                return $orderResponse;
            }

            return null;
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @param string $orderRef
     * @return CollectResponse
     */
    public function collectResponse($orderRef)
    {
        try {
            $response = $this->client->__soapCall(self::METHOD_COLLECT, ['orderRef' => $orderRef]);

            $collect = new CollectResponse();
            $collect->progressStatus = $response->progressStatus;

            if ($collect->progressStatus == CollectResponse::PROGRESS_STATUS_COMPLETE) {
                $collect->userInfo = $response->userInfo;
                $collect->signature = $response->signature;
                $collect->ocspResponse = $response->ocspResponse;
            }

            return $collect;
        } catch (\Exception $exception) {
            return null;
        }
    }
}
