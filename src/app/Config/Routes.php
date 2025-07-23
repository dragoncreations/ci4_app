<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->post('api/coasters', 'Coaster::create');

$routes->post('api/coasters/(:num)/wagons', 'Coaster::add/$1');

$routes->put('api/coasters/(:num)', 'Coaster::update/$1');

$routes->delete('api/coasters/(:num)/wagons/(:num)', 'Coaster::delete/$1/$2');