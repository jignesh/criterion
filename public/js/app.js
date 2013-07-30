(function() {

  // Require.js allows us to configure shortcut alias
  require.config({
    baseUrl: '/js/',
    paths: {
      'jquery': 'libs/jquery.min',
      'jquery.timeago': 'libs/jquery.timeago',
      'angular': 'libs/angular.min'
    },
    shim: {
      'bootstrap': ['jquery'],
      'jquery.timeago': {
        deps: ['jquery'],
        exports: 'jQuery.fn.timeago'
      },
      'angular': {
        exports: 'angular'
      }
    }
  });

  // Load jQuery plugins
  require(
    [
      'libs/jquery.min',
      'libs/jquery.timeago',
      'libs/angular.min'
    ],
    function($) 
    {
      //boot the application
      require(['libs/Criterion'], function(app) 
      {
        Criterion.init();
      });
    }
  );

}).call(this);