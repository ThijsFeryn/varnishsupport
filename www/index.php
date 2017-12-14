<?php
if (php_sapi_name() == 'cli-server' && preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|ttf|woff|json|html|htm)$/', $_SERVER["REQUEST_URI"])) {
    return false;
}
require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Silex\Provider\HttpFragmentServiceProvider;
use Silex\Provider\HttpCacheServiceProvider;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\YamlFileLoader;

$app = new Silex\Application();
$app['locale'] = 'en';
$app['debug'] = true;
$app->register(new Silex\Provider\TwigServiceProvider(), ['twig.path' => __DIR__.'/../views']);
$app->register(new Silex\Provider\TranslationServiceProvider(), ['locale_fallbacks' => ['en','nl']]);
$app->register(new HttpFragmentServiceProvider());
$app->register(new HttpCacheServiceProvider());

$app->register(new Predis\Silex\ClientServiceProvider(), [
    'predis.parameters' => 'tcp://127.0.0.1:6379',
    'predis.options'    => [
        'prefix'  => 'silex:',
        'profile' => '3.0',
    ],
]);

$app->extend('translator', function(Translator $translator, $app) {
    $translator->addLoader('yaml', new YamlFileLoader());
    $translator->addResource('yaml', dirname(__DIR__).'/locales/en.yml', 'en');
    $translator->addResource('yaml',dirname(__DIR__).'/locales/nl.yml', 'nl');
    return $translator;
});

$app->before(function (Request $request) use ($app){
    $request->setLocale($request->getPreferredLanguage());
    $app['translator']->setLocale($request->getPreferredLanguage());
    /*if($request->headers->has('If-None-Match') &&
        $app['predis']->get('etag:'.$request->getUri()) == $request->headers->get('If-None-Match')) {
        return new Response('Not modified',304);
    }*/
});

$app->after(function(Request $request, Response $response) use ($app){
    $response->setETag(md5($response->getContent()));
    $response->headers->set('Content-Length',strlen($response->getContent()));
    $app['predis']->set('etag:'.$request->getUri(),$response->getEtag());
});

$app->get('/', function () use($app) {
    $response =  new Response($app['twig']->render('index.twig'),200);
    //$response
        //->setSharedMaxAge(500)
        //->setPublic();
    return $response;
})->bind('home');

$app->get('/header', function (Request $request) use($app) {
    if($request->cookies->has('username')) {
        $username = $request->cookies->get('username');
    } else {
        $username = '';
    }
    $response =  new Response($app['twig']->render('header.twig',['username'=>$username]),200);
    return $response;
})->bind('header')
  ->before(function (Request $request) use ($app){
      $request->setLocale($request->getPreferredLanguage());
      $app['translator']->setLocale($request->getPreferredLanguage());
      if($request->headers->has('If-None-Match') &&
          $app['predis']->get('etag:'.$request->getUri()) == $request->headers->get('If-None-Match')) {
          return new Response('Not modified, baby',304);
      }
  });
$app->get('/footer', function () use($app) {
    $response =  new Response($app['twig']->render('footer.twig'),200);
    return $response;
})->bind('footer');

$app->get('/nav', function (Request $request) use($app) {
    if($request->cookies->has('username')) {
        $loginlogouturl = 'logout';
        $loginlogoutlabel = 'Logout';
    } else{
        $loginlogouturl = 'login';
        $loginlogoutlabel = 'Login';
    }
    $response =  new Response($app['twig']->render('nav.twig',['loginlogouturl'=>$loginlogouturl,'loginlogoutlabel'=>$loginlogoutlabel]),200);
    return $response;
})->bind('nav')
  ->before(function (Request $request) use ($app){
      $request->setLocale($request->getPreferredLanguage());
      $app['translator']->setLocale($request->getPreferredLanguage());
      if($request->headers->has('If-None-Match') &&
          $app['predis']->get('etag:'.$request->getUri()) == $request->headers->get('If-None-Match')) {
          return new Response('Not modified, baby',304);
      }
  });
$app->get('/info', function (Request $request) use($app) {
    sleep(10);
    $response =  new Response($app['twig']->render('info.twig'),200);
    return $response;
})->bind('info');

$app->get('/login', function (Request $request) use ($app) {
    if($request->cookies->has('username')) {
        return $app->redirect('/');
    }
    return new Response($app['twig']->render('login.twig'),200);
})->bind('login');

$app->get('/logout', function (Request $request) use ($app) {
    unset($_COOKIE['username']);
    setcookie('username', '', time() - 3600, '/');
    return $app->redirect('/');
})->bind('logout');

$app->post('/login', function (Request $request) use ($app) {
    if($request->cookies->has('username')) {
        return $app->redirect('/');
    }
    if ('admin' === $request->get('username') && 'password' === $request->get('password')) {
        setcookie('username',$request->get('username'),time()+3600,'/');
        return $app->redirect('/');
    } else {
        return new Response($app['twig']->render('login.twig'),200);
    }
})->bind('loginpost');
$app->run();