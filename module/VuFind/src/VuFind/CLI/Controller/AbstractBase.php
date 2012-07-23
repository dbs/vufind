<?php
/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\CLI\Controller;
use Zend\Console\Getopt, Zend\Mvc\Controller\AbstractActionController;

/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class AbstractBase extends AbstractActionController
{
    protected $consoleOpts;

    /**
     * Constructor
     */
    public function __construct()
    {
        // This controller should only be accessed from the command line!
        if (PHP_SAPI != 'cli') {
            throw new \Exception('Access denied to command line tools.');
        }

        // Get access to information about the CLI request.
        $this->consoleOpts = new Getopt(array());
    }

    /**
     * Warn the user if VUFIND_LOCAL_DIR is not set.
     *
     * @return void
     */
    protected function checkLocalSetting()
    {
        if (!getenv('VUFIND_LOCAL_DIR')) {
            echo "WARNING: The VUFIND_LOCAL_DIR environment variable is not set.\n";
            echo "This should point to your local configuration directory (i.e. \n";
            echo realpath(APPLICATION_PATH . '/../local') . ").\n";
            echo "Without it, inappropriate default settings may be loaded.\n\n";
        }
    }

    /**
     * Indicate failure.
     *
     * @return void
     */
    protected function getFailureResponse()
    {
        // TODO: better framework integration for response codes
        exit(1);
    }

    /**
     * Indicate success.
     *
     * @return void
     */
    protected function getSuccessResponse()
    {
        // TODO: better framework integration for response codes
        exit(0);
    }
}