<?php

class Export_provisioning_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once dirname(__FILE__) . '/../export_provisioning.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new password($rcube->api);

        $this->assertInstanceOf('export_provisioning', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

