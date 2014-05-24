<?php
/*
 *  Copyright notice
 *
 *  (c) 2014 Daniel Corn <info@cundd.net>, cundd
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 01.04.14
 * Time: 21:55
 */

namespace Cundd\CustomRest;



use Bullet\App;
use Cundd\Rest\Dispatcher;
use Cundd\Rest\HandlerInterface;
use Cundd\Rest\Request;

/**
 * Handler for the credentials authorization
 *
 * @package Cundd\Rest\Handler
 */
class Handler implements HandlerInterface {
	/**
	 * Status logged in
	 */
	const STATUS_LOGGED_IN = 'logged-in';

	/**
	 * Status logged out
	 */
	const STATUS_LOGGED_OUT = 'logged-out';

	/**
	 * Status failed login attempt
	 */
	const STATUS_FAILURE = 'login failure';

	/**
	 * Current request
	 *
	 * @var Request
	 */
	protected $request;

	/**
	 * @var \Cundd\Rest\SessionManager
	 * @inject
	 */
	protected $sessionManager;

	/**
	 * Provider that will check the user credentials
	 *
	 * @var \Cundd\Rest\Authentication\UserProviderInterface
	 * @inject
	 */
	protected $userProvider;

	/**
	 * Sets the current request
	 *
	 * @param \Cundd\Rest\Request $request
	 * @return $this
	 */
	public function setRequest($request) {
		$this->request    = $request;
		return $this;
	}

	/**
	 * Returns the current request
	 *
	 * @return \Cundd\Rest\Request
	 */
	public function getRequest() {
		return $this->request;
	}

	/**
	 * Returns the current status
	 *
	 * @return array
	 */
	public function getStatus() {
		$loginStatus = $this->sessionManager->valueForKey('loginStatus');
		if ($loginStatus === NULL) {
			$loginStatus = self::STATUS_LOGGED_OUT;
		}
		return array(
			'status' => $loginStatus
		);
	}

	/**
	 * Check the given login data
	 *
	 * @param array $sentData
	 * @return array
	 */
	public function checkLogin($sentData) {
		$loginStatus = self::STATUS_LOGGED_OUT;
		if (isset($sentData['username']) && isset($sentData['apikey'])) {
			$username = $sentData['username'];
			$apikey = $sentData['apikey'];

			if ($this->userProvider->checkCredentials($username, $apikey)) {
				$loginStatus = self::STATUS_LOGGED_IN;
			} else {
				$loginStatus = self::STATUS_FAILURE;
			}
			$this->sessionManager->setValueForKey('loginStatus', $loginStatus);
		}
		return array(
			'status' => $loginStatus
		);
	}

	/**
	 * Log out
	 *
	 * @return array
	 */
	public function logout() {
		$this->sessionManager->setValueForKey('loginStatus', self::STATUS_LOGGED_OUT);
		return array(
			'status' => self::STATUS_LOGGED_OUT
		);
	}

	/**
	 * Configure the API paths
	 */
	public function configureApiPaths() {
		$dispatcher = Dispatcher::getSharedDispatcher();

		echo 'bbb';

		/** @var App $app */
		$app = $dispatcher->getApp();

		/** @var AuthHandler */
		$handler = $this;

		$app->path($dispatcher->getPath(), function ($request) use ($handler, $app) {
			$handler->setRequest($request);

			$app->path('login', function($request) use ($handler, $app) {
				$getCallback = function ($request) use ($handler) {
					return $handler->getStatus();
				};
				$app->get($getCallback);

				$loginCallback = function ($request) use ($handler) {
					$dispatcher = Dispatcher::getSharedDispatcher();
					return $handler->checkLogin($dispatcher->getSentData());
				};
				$app->post($loginCallback);
			});

			$app->path('logout', function($request) use ($handler, $app) {
				$getCallback = function ($request) use ($handler) {
					return $handler->logout();
				};
				$app->get($getCallback);
			});
		});
	}
}