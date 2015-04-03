# AcMailer

[![Build Status](https://travis-ci.org/acelaya/ZF2-AcMailer.svg?branch=master)](https://travis-ci.org/acelaya/ZF2-AcMailer)
[![Code Coverage](https://scrutinizer-ci.com/g/acelaya/ZF2-AcMailer/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/acelaya/ZF2-AcMailer/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/acelaya/ZF2-AcMailer/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/acelaya/ZF2-AcMailer/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/acelaya/zf2-acmailer/v/stable.png)](https://packagist.org/packages/acelaya/zf2-acmailer)
[![Total Downloads](https://poser.pugx.org/acelaya/zf2-acmailer/downloads.png)](https://packagist.org/packages/acelaya/zf2-acmailer)
[![License](https://poser.pugx.org/acelaya/zf2-acmailer/license.png)](https://packagist.org/packages/acelaya/zf2-acmailer)

This module, once enabled, registers a service with the key `AcMailer\Service\MailService` that wraps ZF2 mailing functionality, allowing to configure mail information to be used to send emails.
It supports file attachment and template email composition.

### Installation

* * *

Install composer in your project

    curl -s http://getcomposer.org/installer | php

Then run 

    php composer.phar require acelaya/zf2-acmailer:~5.0
    
Add the module `AcMailer` to your `config/application.config.php` file

```php
<?php

return [
    // This should be an array of module namespaces used in the application.
    'modules' => [
        'AcMailer',
        'Application'
    ],
]
```

**IMPORTANT!** Version 5.0.0 has introduced some important BC breaks, so make sure not to update from earlier versions in production without reading this documentation first.

### Usage

* * *

After installation, copy `vendor/acelaya/zf2-acmailer/config/mail.global.php.dist` to `config/autoload/mail.global.php` and customize any of the params. Configuration options are explained later.

Once you get the `AcMailer\Service\MailService` service, a new MailService instance will be returned and you will be allowed to set the body and send the message.

```php
$mailService = $serviceManager->get('AcMailer\Service\MailService');
// The body can be a string, HTML or even a zend\Mime\Message or a Zend\Mime\Part
$mailService->setBody('This is the body');

$result = $mailService->send();
if ($result->isValid()) {
    echo 'Message sent. Congratulations!';
} else {
    if ($result->hasException()) {
        echo sprintf('An error occurred. Exception: \n %s', $result->getException()->getTraceAsString());
    } else {
        echo sprintf('An error occurred. Message: %s', $result->getMessage());
    }
}
```

#### Via controller plugin

Inside controllers, you can access and use the MailService by using the `sendMail` controller plugin. It returns the MailService when no arguments are provided.
 
```php
// In a class extending Zend\Mvc\AbstractController...
$mailService = $this->sendMail();
$mailService->setBody('This is the body');

$result = $mailService->send();
```

But you can pass some basic information, making the email to be sent right away and the result to be returned.

```php
// In a class extending Zend\Mvc\AbstractController...
$result = $this->sendMail(
    'The body',
    'The subject',
    ['recipient_one@domain.com', 'recipient_two@domain.com']
);
// Send another one
$result = $this->sendMail([
    'subject' => 'Hello there!',
    'from' => ['my_address@domain.com', 'John Doe']
]);
```

Adapters configuration can't be provided here, and sholuld be defined at configuration level. Any other information not provided here will be read from configuration.

The plugin accepts a maximum of 7 arguments, which are the body, the subject, the 'to', the 'from', the 'cc', the 'bcc' and the attachments. They can be provided as an associative array too.

#### Rendering views

Instead of setting a plain string, the body of the message can be set from a view script by using `setTemplate` instead of `setBody`. It will use a renderer to render defined template and then set it as the email body internally.

You can set the template as a string and pass the arguments for it.

```php
$mailService = $serviceManager->get('AcMailer\Service\MailService');
$mailService->setTemplate('application/emails/merry-christmas', ['name' => 'John Doe', 'date' => date('Y-m-d')]);
```

You can also set the template as a `Zend\View\Model\ViewModel` object, which will render child templates too.

```php
$mailService = $serviceManager->get('AcMailer\Service\MailService');

$layout = new \Zend\View\Model\ViewModel([
    'name' => 'John Doe',
    'date' => date('Y-m-d')
]);
$layout->setTemplate('application/emails/merry-christmas');

$footer = new \Zend\View\Model\ViewModel();
$footer->setTemplate('application/emails/footer');

$layout->addChild($footer, 'footer');

$mailService->setTemplate($layout);
```

If you are going to send more then one email with different templates but you want all of them to share a common layout, you can set a defaultLayout too.

```php
$mailService = $serviceManager->get('AcMailer\Service\MailService');
$mailService->setDefaultLayout(new AcMailer\View\DefaultLayout(
    'application/emails/layout',
    [
        'title' => 'Something',
    ],
    'captureToKey' // This is the capture to for the template inside the layout
));

// From this point, all the templates will be set as children of the previous layout
$mailService->setTemplate(
    'application/emails/merry-christmas',
    ['name' => 'John Doe', 'date' => date('Y-m-d')]
);
$mailService->send();

$mailService->setTemplate(
    'application/emails/another',
    ['doo' => 'bar']
);
$mailService->send();
```

The renderer that is internally used can be changed to another one (like Twig or Blade). It just needs to implement `Zend\View\Renderer\RendererInterface`.

By default AcMailer uses the default `ViewRenderer` service via an alias, `mailviewrenderer`. You can override that alias in your `service_manager` configuration in order to change the renderer service to be used (thanks to [kusmierz](https://github.com/kusmierz)):

```php

return [
    'service_manager' => [
        'aliases' => [
            'mailviewrenderer' => 'ZfcTwigRenderer',
        ],
    ],
];
```

Alternatively you can just set it via setter: `$mailService->setRenderer($renderer);`.

#### Rendering in CLI executions

When running a ZF2 application from the console, the default `ViewRenderer` service is not created. In that case a `Zend\View\Renderer\PhpRenderer` is created on the fly so that templates can be properly rendered.

It has access to `view_manager` and `view_helpers` configuration, so template resolution will properly work and view helpers (both standard and custom) will be accessible from rendered templates.

If you overriden the `mailviewrenderer` service alias with your own view renderer, then that will be used instead of creating one on the fly.

It is safe to use this module to send emails from cron jobs and such.

#### Email charset

The email body charset can be set in diferent ways.

**String body**: Use the second argument of the `setBody` method.

```php
$mailService->setBody('Hello!!', 'utf-8');
$mailService->setBody('<h1>Hello!!</h1>', 'utf-8');
```

**Template body**: Set a 'charset' property in the second argument of the `setTemplate` method.

```php
$mailService->setTemplate(new Zend\View\Model\ViewModel(), ['charset' => 'utf-8']);
$mailService->setTemplate('application/emails/my-template', [
    'charset' => 'utf-8',
    'date' => date('Y-m-d'),
    'foo' => 'bar',
]);
```

**Mime\Part body**: Either set it in the object before calling `setBody` or pass it as the second argument.

```php
$part = new Zend\Mime\Part();
$part->charset = 'utf-8';
$mailService->setBody($part);

// Providing a charset will overwrite the Mime\Part's charset
$mailService->setBody($part, 'utf-8');
```

#### Attachments

Files can be attached to the email before sending it by providing their paths with `addAttachment`, `addAttachments` or `setAttachments` methods.
At the moment we call `send`, all the files that already exist will be attached to the email.

```php
$mailService->addAttachment('data/mail/attachments/file1.pdf');
$mailService->addAttachment('data/mail/attachments/file2.pdf', 'different-filename.pdf');

// Add two more attachments to the list
$mailService->addAttachments([
    'another-name.pdf' => 'data/mail/attachments/file3.pdf',
    'data/mail/attachments/file4.zip'
]);
// At this point there is 4 attachments ready to be sent with the email

// If we call this, all previous attachments will be discarded
$mailService->setAttachments([
    'data/mail/attachments/another-file1.pdf',
    'name-to-be-displayed.png' => 'data/mail/attachments/another-file2.png'
]);

// A good way to remove all attachments is to call this
$mailService->setAttachments([]);
```

The files will be attached with their real name unless you provide an alternative name as the key of the array element in `addAttachments` and `setAttachments` or as the second argument in `addAttachment`.

**Attention!!** Be careful when attaching files to your email programatically.

Attached images can be displayed inmail by setting the `cid` to the image filename in the image tag like this (thanks to [omarev](https://github.com/acelaya/ZF2-AcMailer/pull/32)). The alternative name should be used if provided.

```html
<img alt="This is an attached image" src="cid:image-filename.jpg">
```

#### Customize the Message

If mail options does not fit your needs or you need to update them at runtime, the message wrapped by the MailService can be customized by getting it before calling `send()`.

```php
$message = $mailService->getMessage();
$message->setSubject('This is the subject')
        ->addTo('foobar@example.com')
        ->addTo('another@example.com')
        ->addBcc('hidden@domain.com');

$result = $mailService->send();
```

If you are using a `Zend\Mail\Transport\File` as the transport object and need to change any option at runtime do this

```php
$mailService = $serviceManager->get('AcMailer\Service\MailService');
$mailService->getTransport()->getOptions()->setPath('dynamically/generated/folder');
$result = $mailService->send();
```

### Event management

* * *

This module comes with a built-in event system.
- An event is triggered before the mail is sent (`MailEvent::EVENT_MAIL_PRE_SEND`).
- If everything was OK another event is triggered (`MailEvent::EVENT_MAIL_POST_SEND`) after the email has been sent.
- If an error occured, an error event is triggered (`MailEvent::EVENT_MAIL_SEND_ERROR`).

Managing mail events is as easy as extending `AcMailer\Event\AbstractMailListener`. It provides the `onPreSend`, `onPostSend` and `onSendError` methods, which get a `MailEvent` parameter that can be used to get the `MailService` which triggered the event or the `MailResult` produced.

Then attach the listener object to the `MailService` and the corresponding method will be automatically called when calling the `send` method.

```php
$mailListener = new \Application\Event\MyMailListener();
$mailService->attachMailListener($mailListener);

$mailService->send(); // Mail events will be triggered at this moment
```

If you want to detach a previously attached event manager, just call detach MailListener like this.

```php
$mailListener = new \Application\Event\MyMailListener();
$mailService->attachMailListener($mailListener);

// Some conditions occurred which made us not to want the events to be triggered any more on this listener
$mailService->detachMailListener($mailListener);

$mailService->send(); // The events on the $mailListener won't be triggered.
```

The `MailResult` will always be null when the event `EVENT_MAIL_PRE_SEND` is triggered, since the email hasn't been sent yet.

Any `Zend\Mail` exception will be catched, producing a `EVENT_MAIL_SEND_ERROR` instead. If any other kind of exception occurs, the same event will be triggered, but the exception will be rethrown in the form of an `AcMailer\Exception\MailException`. The event's wrapped exception will be the original exception.

### Configuration options

* * *

**Important!** The configuration has completly changed from v5.0.0 and is not compatible with earlier versions. If you want to upgrade, please, read this section.

When the mail service is created, it automatically tries to find the `acmailer_options` config key under the global configuration, and it is initialized with it. An example configuration file is provided in `vendor/acelaya/zf2-acmailer/config/mail.global.php.dist` that you can copy to `config/autoload/mail.global.php`.

Related configuration options are grouped under common keys.

- **mail_adapter**: Tells the mail service what type of transport adapter should be used. Any instance or classname implementing `Zend\Mail\Transport\TransportInterface` is valid. It is also possible to define a service and it will be automatically fetched.
- **transport**: It is an alias for the **mail_adapter** option. Just use one or another.
- **message_options**: Wraps message-related options
    - **from**: From email address.
    - **from_name**: From name to be displayed.
    - **to**: It can be a string with one destination email address or an array of multiple addresses.
    - **cc**: It can be a string with one carbon copy email address or an array of multiple addresses.
    - **bcc**: It can be a string with one blind carbon copy email address or an array of multiple addresses.
    - **subject**: Default email subject.
    - **body**: Wraps body configuration, like template, content or charset
        - **content**: A string with the default content body, either in plan text or HTML. Use it for simple emails.
        - **charset**: Charset to be used in the email body. Default value is utf-8
        - **use_template**: Tells if the body should be created from a template. If true, the **template** options will be used, ignoring the **content** option. Default value is `false`.
        - **template**: Wraps the configuration to create emails from templates.
            - *path*: Path of the template. The same used while setting the template of a ViewModel (ie. 'application/index/list').
            - *params*: Array with key-value pairs with parameters to be sent to the template.
            - *children*: Array with children templates to be used within the main template (layout). Each one of them can have its own children that will be recursively rendered. Look at `vendor/acelaya/zf2-acmailer/config/mail.global.php.dist` for details.
            - *default_layout*: Wraps the information to set a default layout for all the templates
                - *path*: Path of the layout. The same used while setting the template of a ViewModel (ie. 'application/index/list'). Default value is null, so that a default layout is not used.
                - *params*: Array with key-value pairs with parameters to be sent to the layout. By default is an empoty array.
                - *template_capture_to*: Capture to value for each template inside this layout. Default value is 'content'.
    - **attachments**: Wraps the configuration of attachements.
        - *files*: Array of files to be attached. Can be an associative array where keys are attachment names and values are file paths.
        - *dir*: Directory to iterate.
            - *iterate*: If it is not true, the directory won't be iterated. Default value is false.
            - *path*: The path of the directory to iterate looking for files. This files will be attached with their real names.
            - *recursive*: True or false. Tells if nested directories should be recursively iterated too.
- **smtp_options**: Wraps the SMTP configuration that is used when the mail adapter is a `Zend\Mail\Transport\Smtp` instance.
    - **host**: IP address or server name of the SMTP server. Default value is 'localhost'.
    - **port**: SMTP server port. Default value is 25.
    - **connection_class**: The connection class used for authentication. Values are 'smtp', 'plain', 'login' or 'crammd5'
    - **connection_config**
        - *username*: Username to be used for authentication against the SMTP server. If none is provided the `message_options/from` option will be used for this purpose.
        - *smtp_password*: Password to be used for authentication against the SMTP server.
        - *ssl*: Defines the type of connection encryption against the SMTP server. Values are 'ssl', 'tls' or `false` to disable encryption.
- **file_options**: Wraps the files configuration that will be used when the mail adapter is a `Zend\Mail\Transport\File` instance
    - **path**: Directory where the email will be saved.
    - **callback**: Callback used to get the filename of the email.
- **mail_listeners**: An array of mail listeners that will be automatically attached to the service once created. They can be either `ZcMailer\Event\MailListenerInterface` instances or strings that will be used to fetch a service if exists or lazily instantiate an object. This is an empty array by default.

### Testing

* * *

`AcMailer\Service\MailService` should be injected into Controllers or other Services which you probably need to test. It implements `AcMailer\Service\MailServiceInterface` for this purpose, but even a `MailServiceMock` is included.
It allows user to define if the message should or should not fail when `send` method is called, by calling `setForceError` method.
You can even know if `send` method was called after any action by calling `isSendMethodCalled`.

```php
$mailServiceMock = new \AcMailer\Service\MailServiceMock();
$mailServiceMock->isSendMethodCalled(); // This will return false at this point

// Force an error
$mailServiceMock->setForceError(true);
$result = $mailServiceMock->send();
$result->isValid(); // This will return false because we forced an error

$mailServiceMock->isSendMethodCalled(); // This will return true at this point

// Force a success
$mailServiceMock->setForceError(false);
$result = $mailServiceMock->send();
$result->isValid(); // This will return true in this case
```

* * *

Thanks to [PhpStorm](https://www.jetbrains.com/phpstorm/) for their support, since they have provided me an OSS license to work on this project.

![PhpStorm](https://www.jetbrains.com/phpstorm/documentation/docs/logo_phpstorm.png)