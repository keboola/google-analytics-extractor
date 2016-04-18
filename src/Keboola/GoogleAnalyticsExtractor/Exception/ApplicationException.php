<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/12/15
 * Time: 12:45
 */

namespace Keboola\GoogleAnalyticsExtractor\Exception;

class ApplicationException extends \Exception
{
    /**
     * @var array
     */
    protected $data;
    public function __construct($message = "", $code = 0, \Exception $previous = null, $data = [])
    {
        $this->setData($data);
        parent::__construct($message, $code, $previous);
    }
    /**
     * @param array $data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }
    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
}
