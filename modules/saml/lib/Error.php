<?php

declare(strict_types=1);

namespace SimpleSAML\Module\saml;

use SimpleSAML\Assert\Assert;
use SimpleSAML\SAML2\Constants;
use SimpleSAML\SAML2\XML\samlp\Status;
use SimpleSAML\SAML2\XML\samlp\StatusCode;
use Throwable;

/**
 * Class for representing a SAML 2 error.
 *
 * @package SimpleSAMLphp
 */

class Error extends \SimpleSAML\Error\Exception
{
    /**
     * The top-level status code.
     *
     * @var \SimpleSAML\SAML2\XML\samlp\Status
     */
    private string $status;


    /**
     * Create a SAML 2 error.
     *
     * @param \SimpleSAML\SAML2\XML\samlp\Status $status  The top-level status code.
     * @param \Throwable|null $cause  The cause of this exception. Can be NULL.
     */
    public function __construct(
        Status $status,
        Throwable $cause = null
    ) {
        $st = self::shortStatus($status->getStatusCode());

        foreach ($status->getStatusCode()->getSubCodes() as $subStatus) {
            $st .= '/' . self::shortStatus($subStatus);
        }

        $statusMessage = $status->getStatusMessage();
        if ($statusMessage !== null) {
            $st .= ': ' . $statusMessage;
        }
        parent::__construct($st, 0, $cause);

        $this->status = $status;
    }


    /**
     * Get the top-level status code.
     *
     * @return \SimpleSAML\SAML2\XML\samlp\Status  The top-level status code.
     */
    public function getStatus(): Status
    {
        return $this->status;
    }


    /**
     * Create a SAML2 error from an exception.
     *
     * This function attempts to create a SAML2 error with the appropriate
     * status codes from an arbitrary exception.
     *
     * @param \Throwable $exception  The original exception.
     * @return \SimpleSAML\Error\Exception  The new exception.
     */
    public static function fromException(Throwable $exception): \SimpleSAML\Error\Exception
    {
        if ($exception instanceof \SimpleSAML\Module\saml\Error) {
            // Return the original exception unchanged
            return $exception;
        } else {
            $e = new self(
                new Status(
                    new StatusCode(Constants::STATUS_RESPONDER),
                    get_class($exception) . ': ' . $exception->getMessage()
                ),
                $exception
            );
        }

        return $e;
    }


    /**
     * Create a normal exception from a SAML2 error.
     *
     * This function attempts to reverse the operation of the fromException() function.
     * If it is unable to create a more specific exception, it will return the current
     * object.
     *
     * @see \SimpleSAML\Module\saml\Error::fromException()
     *
     * @return \SimpleSAML\Error\Exception  An exception representing this error.
     */
    public function toException(): \SimpleSAML\Error\Exception
    {
        $e = null;

        switch ($this->status->getStatusCode()->getValue()) {
            case Constants::STATUS_RESPONDER:
                foreach ($this->status->getStatusCode()->getSubCodes() as $subCode) {
                    if ($subCode->getValue() === Constants::STATUS_NO_PASSIVE) {
                        $e = new \SimpleSAML\Module\saml\Error\NoPassive(
                            Constants::STATUS_RESPONDER,
                            $this->status->getStatusMessage()
                        );
                        break;
                    }
                }
                break;
        }

        if ($e === null) {
            return $this;
        }

        return $e;
    }


    /**
     * Create a short version of the status code.
     *
     * Remove the 'urn:oasis:names:tc:SAML:2.0:status:'-prefix of status codes
     * if it is present.
     *
     * @param \SimpleSAML\SAML2\XML\samlp\StatusCode $statusCode  The status code.
     * @return string  A shorter version of the status code.
     */
    private static function shortStatus(StatusCode $statusCode): string
    {
        return preg_replace('/^urn:oasis:names:tc:SAML:2.0:status:/', '', $statusCode->getValue());
    }
}
