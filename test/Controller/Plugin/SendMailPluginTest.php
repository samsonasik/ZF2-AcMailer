<?php
namespace AcMailerTest\Controller\Plugin;

use AcMailer\Controller\Plugin\SendMailPlugin;
use AcMailer\Service\MailServiceInterface;
use AcMailer\Service\MailServiceMock;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Stdlib\ArrayUtils;
use Zend\View\Model\ViewModel;

/**
 * Class SendMailPluginTest
 * @author Alejandro Celaya Alastrué
 * @link http://www.alejandrocelaya.com
 */
class SendMailPluginTest extends TestCase
{
    /**
     * @var SendMailPlugin
     */
    private $plugin;
    /**
     * @var MailServiceInterface
     */
    private $service;

    public function setUp()
    {
        $this->service = new MailServiceMock();
        $this->plugin = new SendMailPlugin($this->service);
    }

    public function testInvokeWithNoArgumentsReturnsTheService()
    {
        $this->assertInstanceOf('AcMailer\Service\MailServiceInterface', $this->plugin->__invoke());
    }

    public function testFirstArgumentArrayIsTreatedAsConfig()
    {
        $config = [
            'body' => 'foobar',
            'subject' => 'barfoo'
        ];

        $result = $this->plugin->__invoke($config);
        $this->assertInstanceOf('AcMailer\Result\ResultInterface', $result);
        $this->assertEquals($config['body'], $this->service->getMessage()->getBody());
        $this->assertEquals($config['subject'], $this->service->getMessage()->getSubject());
    }

    public function testArgumentsAreProperlyMapped()
    {
        $result = $this->plugin->__invoke(
            'theBody',
            'theSubject',
            ['foobar'],
            ['from@me.com' => 'From Me'],
            ['cc'],
            ['bcc'],
            ['attachments/attachment1.zip', 'attachments/attachment2.zip']
        );

        $this->assertInstanceOf('AcMailer\Result\ResultInterface', $result);
        $this->assertEquals('theBody', $this->service->getMessage()->getBody());
        $this->assertEquals('theSubject', $this->service->getMessage()->getSubject());
        $this->assertEquals('foobar', $this->service->getMessage()->getTo()->current()->getEmail());
        $this->assertEquals('from@me.com', $this->service->getMessage()->getFrom()->current()->getEmail());
        $this->assertEquals('From Me', $this->service->getMessage()->getFrom()->current()->getName());
        $this->assertEquals('cc', $this->service->getMessage()->getCc()->current()->getEmail());
        $this->assertEquals('bcc', $this->service->getMessage()->getBcc()->current()->getEmail());
    }

    public function testFromIsValidAsString()
    {
        $result = $this->plugin->__invoke('theBody', 'theSubject', ['foobar'], 'from@me.com');

        $this->assertInstanceOf('AcMailer\Result\ResultInterface', $result);
        $this->assertEquals('from@me.com', $this->service->getMessage()->getFrom()->current()->getEmail());
    }

    public function testBodyIsValidAsViewModel()
    {
        $result = $this->plugin->__invoke(new ViewModel());

        $this->assertInstanceOf('AcMailer\Result\ResultInterface', $result);
        $this->assertEquals('ViewModel body', $this->service->getMessage()->getBody());
    }

    public function testMailServiceAwareness()
    {
        $this->assertSame($this->service, $this->plugin->getMailService());
        $anotherService = new MailServiceMock();
        $this->assertSame($this->plugin, $this->plugin->setMailService($anotherService));
        $this->assertSame($anotherService, $this->plugin->getMailService());
    }
}