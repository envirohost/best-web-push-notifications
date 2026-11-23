<?php

/**
 * Class LoggerTest
 *
 * @package BWPN
 */

/**
 * Test the BWPN_Lib_Logger class
 */
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

class LoggerTest extends WP_UnitTestCase
{
	use ArraySubsetAsserts;

    public function set_up()
    {
        parent::set_up();
        activate_bwpn();
    }

    public function tear_down()
    {
        deactivate_bwpn();
        parent::tear_down();
    }

    /**
     * Test logger
     */
    public function test_logger_enabled()
    {
    	$writter = new BWPN_Lib_Log_Db();
    	BWPN_Lib_Log::init($writter, BWPN_Lib_Log::DEBUG);
        BWPN_Lib_Log::enable();
        BWPN_Lib_Log::debug("debug message");
        BWPN_Lib_Log::info("info message");
        BWPN_Lib_Log::warning("warning message");
        BWPN_Lib_Log::error("error message");
        $logs = BWPN_Lib_Db::get_logs(0, 10);
        BWPN_Lib_Log::disable();

        $expected_debug = array(
            'level' => 'debug',
            'message' => 'debug message'
        );
        $expected_info = array(
            'level' => 'info',
            'message' => 'info message'
        );
        $expected_warning = array(
            'level' => 'warning',
            'message' => 'warning message'
        );
        $expected_error = array(
            'level' => 'error',
            'message' => 'error message'
        );
        $this->assertArraySubset( $expected_debug, (array) $logs[0]);
        $this->assertArraySubset( $expected_info, (array) $logs[1]);
        $this->assertArraySubset( $expected_warning, (array) $logs[2]);
        $this->assertArraySubset( $expected_error, (array) $logs[3]);
        $this->assertSame(4, count($logs));
    }

    /**
     * Test logger when disabled
     */
    public function test_logger_disabled()
    {
        BWPN_Lib_Log::disable();

        BWPN_Lib_Log::debug("debug message");
        BWPN_Lib_Log::info("info message");
        BWPN_Lib_Log::warning("warning message");
        BWPN_Lib_Log::error("error message");
        $logs = BWPN_Lib_Db::get_logs(0, 10);

        $this->assertSame(0, count($logs));
    }
}