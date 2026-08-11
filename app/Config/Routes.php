<?php

namespace Config;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();

/*
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
// The Auto Routing (Legacy) is very dangerous. It is easy to create vulnerable apps
// where controller filters or CSRF protection are bypassed.
// If you don't want to define all routes, please use the Auto Routing (Improved).
// Set `$autoRoutesImproved` to true in `app/Config/Feature.php` and set the following to true.
// $routes->setAutoRoute(false);

/*
 * --------------------------------------------------------------------
 * Route Definitions
 * --------------------------------------------------------------------
 */

// We get a performance increase by specifying the default
// route since we don't have to scan directories.
$routes->get('/', 'Home::index');
$routes->post('formInfo', 'Home::infoReserva');
$routes->post('checkClosure', 'Home::checkClosure');
$routes->get('getUpcomingClosure', 'Home::getUpcomingClosure');
$routes->get('getDataMp', 'Home::getDataMp');
$routes->get('deleteRejected', 'Home::deleteRejected');
$routes->get('phpinfo', 'Debug::phpinfo');

$routes->post('setPreference', 'MercadoPago::setPreference');
$routes->post('cancelPendingMpReservation', 'MercadoPago::cancelPendingMpReservation');
$routes->post('savePreferenceIds', 'MercadoPago::savePreferenceIds');
$routes->get('payment/success', 'MercadoPago::success');
$routes->get('payment/failure', 'MercadoPago::failure');
$routes->post('payment/webhook', 'MercadoPago::webhook');

$routes->group('auth', function ($routes) {
    $routes->post('register', 'Auth::dbRegister');
    $routes->get('logOut', 'Auth::log_out');
    $routes->get('login', 'Auth::index');
    $routes->post('login', 'Auth::login');
    $routes->get('register', 'Auth::register');
});

$routes->post('saveBooking', 'Bookings::saveBooking');
$routes->get('getBookings/(:any)', 'Bookings::getBookings/$1');
$routes->get('getBooking/(:any)', 'Bookings::getBooking/$1');
$routes->get('bookingPdf/(:any)', 'Bookings::bookingPdf/$1');


$routes->get('getFields', 'Fields::getFields');
$routes->get('getField/(:any)', 'Fields::getField/$1');

$routes->get('getRate', 'Rate::getRate');

$routes->get('getOffersRate', 'Offers::getOffersRate');

$routes->get('getNocturnalTime', 'Time::getNocturnalTime');

$routes->get('customers/register', 'Customers::register');
$routes->post('customers/register', 'Customers::dbRegister');
$routes->get('getCustomer/(:any)', 'Customers::getCustomer/$1');
$routes->post('customers/getApplicableOffer', 'Customers::getApplicableOffer');

$routes->get('getUser/(:any)', 'Users::getUser/$1');
$routes->post('editUser', 'Users::editUser');


$routes->group('', ['filter' => 'auth'], function ($routes) {

    $routes->get('upload', 'Upload::index');
    $routes->post('upload/upload', 'Upload::upload');
    $routes->get('deleteBackground', 'Upload::deleteBackground');

    $routes->get('configMpView', 'Superadmin::configMpView');
    $routes->post('configMp', 'Superadmin::configMp');
    $routes->get('abmAdmin', 'Superadmin::index');
    $routes->post('saveField', 'Superadmin::saveField');
    $routes->post('editField/(:any)', 'Superadmin::editField/$1');
    $routes->post('saveService', 'Superadmin::saveService');
    $routes->post('editService/(:any)', 'Superadmin::editService/$1');
    $routes->post('getActiveBookings', 'Superadmin::getActiveBookings');
    $routes->post('getAnnulledBookings', 'Superadmin::getAnnulledBookings');
    $routes->post('getBookingIssues', 'Superadmin::getBookingIssues');
    $routes->post('checkCancelReservations', 'Superadmin::checkCancelReservations');
    $routes->post('saveCancelReservations', 'Superadmin::saveCancelReservations');
    $routes->post('updateCancelReservation', 'Superadmin::updateCancelReservation');
    $routes->post('getCancelReservations', 'Superadmin::getCancelReservations');
    $routes->post('deleteCancelReservation', 'Superadmin::deleteCancelReservation');
    $routes->post('saveConfigGeneral', 'Superadmin::saveConfigGeneral');
    $routes->post('getAdminLogs', 'Superadmin::getAdminLogs');
    $routes->post('deleteUser/(:any)', 'Superadmin::deleteUser/$1');

    $routes->post('saveTime', 'Time::saveTime');
    $routes->get('getTime', 'Time::getTime');

    $routes->post('confirmMP', 'Bookings::confirmMP');

    $routes->post('completePayment/(:any)', 'Bookings::completePayment/$1');
    $routes->post('getReports', 'Bookings::getReports');
    $routes->post('getMpPayments', 'Bookings::getMpPayments');
    $routes->post('cancelBooking', 'Bookings::cancelBooking');
    $routes->post('editBooking', 'Bookings::editBooking');
    $routes->post('saveAdminBooking', 'Bookings::saveAdminBooking');
    $routes->get('generateReportPdf/(:any)/(:any)/(:any)', 'Bookings::generateReportPdf/$1/$2/$3');
    $routes->get('generatePaymentsReportPdf/(:any)/(:any)', 'Bookings::generatePaymentsReportPdf/$1/$2');

    $routes->post('saveRate', 'Rate::saveRate');

    $routes->post('saveOfferRate', 'Offers::saveOfferRate');

    $routes->group('customers', function ($routes) {
        $routes->get('deleteCustomer/(:any)', 'Customers::delete/$1');
        $routes->get('registerWindow', 'Customers::registerWindow');
        $routes->post('editCustomer', 'Customers::edit');
        $routes->post('editCustomerAjax', 'Customers::editAjax');
        $routes->get('editWindow/(:any)', 'Customers::editWindow/$1');
        $routes->get('getCustomer/(:any)', 'Customers::getCustomer/$1');
        $routes->get('getCustomers', 'Customers::getCustomers');
        $routes->get('getCustomersWithOffer', 'Customers::getCustomersWithOffer');
        $routes->post('registerAjax', 'Customers::registerAjax');
        $routes->post('setOfferTrue', 'Customers::setOfferTrue');
        $routes->post('setOfferFalse', 'Customers::setOfferFalse');
    });
});


/*
 * --------------------------------------------------------------------
 * Additional Routing
 * --------------------------------------------------------------------
 *
 * There will often be times that you need additional routing and you
 * need it to be able to override any defaults in this file. Environment
 * based routes is one such time. require() additional route files here
 * to make that happen.
 *
 * You will have access to the $routes object within that file without
 * needing to reload it.
 */
if (is_file(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
