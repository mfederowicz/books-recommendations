<?php

declare(strict_types=1);

namespace App\Tests\phpunit\Unit\Form;

use App\Entity\User;
use App\Form\RegisterUserAccount;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RegisterUserAccountTest extends TestCase
{
    public function testFormExtendsAbstractType(): void
    {
        $formType = new RegisterUserAccount();

        $this->assertInstanceOf(AbstractType::class, $formType);
    }

    public function testFormHasCorrectDataClass(): void
    {
        $formType = new RegisterUserAccount();
        $optionsResolver = new OptionsResolver();
        $formType->configureOptions($optionsResolver);
        $options = $optionsResolver->resolve([]);

        $this->assertEquals(User::class, $options['data_class']);
    }

    public function testBuildFormAddsRequiredFields(): void
    {
        $formType = new RegisterUserAccount();

        // Mock FormBuilderInterface
        $builder = $this->createMock(FormBuilderInterface::class);

        // Sprawdź czy add jest wywoływane dokładnie 2 razy (dla email i password)
        $builder->expects($this->exactly(2))
            ->method('add')
            ->willReturn($builder);

        // Call buildForm method
        $formType->buildForm($builder, []);
    }

    public function testFormClassExistsAndIsInstantiable(): void
    {
        $formType = new RegisterUserAccount();

        $this->assertInstanceOf(RegisterUserAccount::class, $formType);
        $this->assertIsObject($formType);
    }

    public function testFormImplementsRequiredMethods(): void
    {
        $formType = new RegisterUserAccount();

        // Sprawdź czy klasa ma wymagane metody
        $this->assertTrue(method_exists($formType, 'buildForm'));
        $this->assertTrue(method_exists($formType, 'configureOptions'));
    }
}
