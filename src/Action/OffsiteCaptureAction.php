<?php
namespace Payum\OmnipayBridge\Action;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Request\Capture;
use Payum\Core\Reply\HttpPostRedirect;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\GetHttpRequest;

class OffsiteCaptureAction extends BaseApiAwareAction implements GatewayAwareInterface
{
    /**
     * @var GatewayInterface
     */
    protected $gateway;

    /**
     * {@inheritDoc}
     */
    public function setGateway(GatewayInterface $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * {@inheritDoc}
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $details = ArrayObject::ensureArrayObject($request->getModel());

        if ($details['_status']) {
            return;
        }

        if (false == $details['returnUrl'] && $request->getToken()) {
            $details['returnUrl'] = $request->getToken()->getTargetUrl();
        }

        if (false == $details['cancelUrl'] && $request->getToken()) {
            $details['cancelUrl'] = $request->getToken()->getTargetUrl();
        }

        if (false == $details['clientIp']) {
            $this->gateway->execute($httpRequest = new GetHttpRequest);

            $details['clientIp'] = $httpRequest->clientIp;
        }

        if (isset($details['_completeCaptureRequired'])) {
            $response = $this->omnipayGateway->completePurchase($details->toUnsafeArray())->send();

            unset($details['_completeCaptureRequired']);
        } else {
            $response = $this->omnipayGateway->purchase($details->toUnsafeArray())->send();
        }

        if ($response->isRedirect()) {
            $details['_completeCaptureRequired'] = 1;
            
            if ($response->getRedirectMethod() == 'POST') {
                throw new HttpPostRedirect($response->getRedirectUrl(), $response->getRedirectData());
            }
            else {
                throw new HttpRedirect($response->getRedirectUrl());
            }
        }

        $details['_reference']      = $response->getTransactionReference();
        $details['_status']         = $response->isSuccessful() ? 'captured' : 'failed';
        $details['_status_code']    = $response->getCode();
        $details['_status_message'] = $response->isSuccessful() ? '' : $response->getMessage();
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
