<?php

/**
 * Copyright 2019 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\Tests\apigee_edge\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * ApigeeEdgeManagementCliService Edge tests.
 *
 * Make sure Edge API works as expected for the ApigeeEdgeManagementCliService.
 *
 * These tests validate Edge API request/responses needed for
 * ApigeeEdgeManagementCliService are valid.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class ApigeeEdgeManagementCliServiceTest extends KernelTestBase implements ServiceModifierInterface {

  protected const TEST_ROLE_NAME = 'temp_role';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * Apigee API endpoint.
   *
   * @var array|false|string
   */
  protected $endpoint;

  /**
   * Apigee Edge organization.
   *
   * @var array|false|string
   */
  protected $organization;

  /**
   * Email of an account with the organization admin Apigee role.
   *
   * @var array|false|string
   */
  protected $orgadminEmail;

  /**
   * The password of the orgadmin account.
   *
   * @var array|false|string
   */
  protected $orgadminPassword;

  /**
   * A GuzzleHttp\Client object.
   *
   * @var object|null
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $environment_vars = [
      'APIGEE_EDGE_ENDPOINT',
      'APIGEE_EDGE_ORGANIZATION',
      'APIGEE_EDGE_USERNAME',
      'APIGEE_EDGE_PASSWORD',
    ];

    foreach ($environment_vars as $environment_var) {
      if (!getenv($environment_var)) {
        $this->markTestSkipped('Environment variable ' . $environment_var . ' is not set, cannot run tests. See CONTRIBUTING.md for more information.');
      }
    }

    // Get environment variables for Edge connection.
    $this->endpoint = getenv('APIGEE_EDGE_ENDPOINT');
    $this->organization = getenv('APIGEE_EDGE_ORGANIZATION');
    $this->orgadminEmail = getenv('APIGEE_EDGE_USERNAME');
    $this->orgadminPassword = getenv('APIGEE_EDGE_PASSWORD');

    /** @var \GuzzleHttp\Client $client */
    $this->httpClient = $this->container->get('http_client');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $url = $this->endpoint . '/o/' . $this->organization . '/userroles/' . self::TEST_ROLE_NAME;
    $response = $this->httpClient->get($url, [
      'http_errors' => FALSE,
      'auth' => [$this->orgadminEmail, $this->orgadminPassword],
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);

    if ($response->getStatusCode() == 200) {
      $url = $this->endpoint . '/o/' . $this->organization . '/userroles/' . self::TEST_ROLE_NAME;
      $this->httpClient->delete($url, [
        'auth' => [$this->orgadminEmail, $this->orgadminPassword],
        'headers' => [
          'Accept' => 'application/json',
          'Content-Type' => 'application/json',
        ],
      ]);
    }
    parent::tearDown();

  }

  /**
   * Fix for outbound HTTP requests fail with KernelTestBase.
   *
   * See comment #10:
   * https://www.drupal.org/project/drupal/issues/2571475#comment-11938008
   */
  public function alter(ContainerBuilder $container) {
    $container->removeDefinition('test.http_client.middleware');
  }

  /**
   * Test actual call to Edge API that IsValidEdgeCredentials() uses.
   */
  public function testIsValidEdgeCredentialsEdgeApi() {
    $url = $this->endpoint . '/o/' . $this->organization;
    $response = $this->httpClient->get($url, [
      'auth' => [$this->orgadminEmail, $this->orgadminPassword],
      'headers' => ['Accept' => 'application/json'],
    ]);

    $body = json_decode($response->getBody());
    $this->assertTrue(isset($body->name), 'Edge org entity should contain "name" attribute.');
    $this->assertEquals($this->organization, $body->name, 'Edge org name attribute should match org being called in url.');
  }

  /**
   * Test Edge API response/request for doesRoleExist()
   */
  public function testDoesRoleExist() {
    // Role should not exist.
    $url = $this->endpoint . '/o/' . $this->organization . '/userroles/' . self::TEST_ROLE_NAME;

    $response = $this->httpClient->get($url, [
      'http_errors' => FALSE,
      'auth' => [$this->orgadminEmail, $this->orgadminPassword],
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);
    $this->assertEquals('404', $response->getStatusCode(), 'Role that does not exist should return 404.');

  }

  /**
   * Test Edge API for creating role and setting permissions.
   */
  public function testCreateEdgeRoleAndSetPermissions() {

    $url = $this->endpoint . '/o/' . $this->organization . '/userroles';
    $response = $this->httpClient->post($url, [
      'body' => json_encode([
        'role' => [self::TEST_ROLE_NAME],
      ]),
      'auth' => [$this->orgadminEmail, $this->orgadminPassword],
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);
    $this->assertEquals('201', $response->getStatusCode(), 'Role should be created.');

    // Add permissions to this role.
    $url = $this->endpoint . '/o/' . $this->organization . '/userroles/' . self::TEST_ROLE_NAME . '/permissions';
    $body = json_encode([
      'path' => '/developers',
      'permissions' => ['get', 'put', 'delete'],
    ]);
    $response = $this->httpClient->post($url, [
      'body' => $body,
      'auth' => [$this->orgadminEmail, $this->orgadminPassword],
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);
    $this->assertEquals('201', $response->getStatusCode(), 'Permission on role should be created.');
  }

}
