<?php
/**
 * SB-Tech — old-input tests (failed-form repopulation).
 *
 * Covers: sensitive-field detection, POST snapshot, one-request flash aging,
 * and the old() default fallback.
 */

use PHPUnit\Framework\TestCase;

class OldInputTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['REQUEST_METHOD']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testSensitiveKeysAreDetected()
    {
        $this->assertTrue(is_sensitive_input_key('password'));
        $this->assertTrue(is_sensitive_input_key('Password_Confirmation'));
        $this->assertTrue(is_sensitive_input_key('current_passwd'));
        $this->assertTrue(is_sensitive_input_key('csrf_token'));
        $this->assertTrue(is_sensitive_input_key('api_token'));
        $this->assertTrue(is_sensitive_input_key('totp_secret'));
        $this->assertFalse(is_sensitive_input_key('client_name'));
        $this->assertFalse(is_sensitive_input_key('company'));
    }

    public function testCaptureDropsSensitiveValues()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'client_name' => 'ACME',
            'password' => 'hunter2',
            'csrf_token' => 'abc',
            'note' => 'hello',
        ];

        capture_old_input();

        $this->assertSame(['client_name' => 'ACME', 'note' => 'hello'], $_SESSION['_old_input_new']);
    }

    public function testCaptureIgnoresNonPostRequests()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = ['client_name' => 'ACME'];

        capture_old_input();

        $this->assertArrayNotHasKey('_old_input_new', $_SESSION);
    }

    public function testAgeExposesOldInputForExactlyOneRequest()
    {
        $_SESSION['_old_input_new'] = ['a' => '1'];

        age_old_input();
        $this->assertSame('1', old('a'));
        $this->assertSame('1', old('a', 'DEF'));

        age_old_input();
        $this->assertSame('DEF', old('a', 'DEF'));
    }

    public function testOldReturnsDefaultWhenMissing()
    {
        $this->assertSame('fallback', old('nope', 'fallback'));
    }
}