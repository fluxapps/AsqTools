<?php
declare(strict_types = 1);

namespace Fluxlabs\Assessment\Tools\Domain\Modules\Settings;

use Fluxlabs\Assessment\Tools\DIC\CtrlTrait;
use Fluxlabs\Assessment\Tools\DIC\HttpTrait;
use Fluxlabs\Assessment\Tools\DIC\KitchenSinkTrait;
use Fluxlabs\Assessment\Tools\DIC\LanguageTrait;
use Fluxlabs\Assessment\Tools\Domain\IObjectAccess;
use Fluxlabs\Assessment\Tools\Domain\Modules\AbstractAsqModule;
use Fluxlabs\Assessment\Tools\Domain\Modules\IAsqModule;
use Fluxlabs\Assessment\Tools\Domain\Modules\IPageModule;
use Fluxlabs\Assessment\Tools\Event\IEventQueue;
use Fluxlabs\Assessment\Tools\Event\Standard\AddTabEvent;
use Fluxlabs\Assessment\Tools\Event\Standard\ForwardToCommandEvent;
use Fluxlabs\Assessment\Tools\Event\Standard\SetUIEvent;
use Fluxlabs\Assessment\Tools\UI\System\TabDefinition;
use Fluxlabs\Assessment\Tools\UI\System\UIData;
use ILIAS\UI\Component\Input\Container\Form\Standard;
use srag\asq\UserInterface\Web\Form\Factory\AbstractObjectFactory;

/**
 * Class SettingsPage
 *
 * @package Fluxlabs\Assessment\Tools
 *
 * @author Fluxlabs AG - Adrian Lüthi <adi@fluxlabs.ch>
 */
class SettingsPage extends AbstractAsqModule implements IPageModule
{
    use LanguageTrait;
    use KitchenSinkTrait;
    use CtrlTrait;
    use HttpTrait;

    const CMD_SHOW_SETTINGS = 'showSettings';
    const CMD_STORE_SETTINGS = 'storeSettings';

    /**
     * @var IAsqModule[]
     */
    private array $modules;

    /**
     * @param IEventQueue $event_queue
     * @param IObjectAccess $access
     * @param IAsqModule[] $modules
     */
    public function __construct(IEventQueue $event_queue, IObjectAccess $access, array $modules)
    {
        parent::__construct($event_queue, $access);

        foreach ($modules as $module) {
            if ($module->getConfigFactory() !== null) {
                $this->modules[] = $module;
            }
        }

        $this->raiseEvent(new AddTabEvent(
            $this,
            new TabDefinition(self::class, $this->txt('asqt_settings'), self::CMD_SHOW_SETTINGS)
        ));
    }

    public function showSettings() : void
    {
        $this->raiseEvent(new SetUIEvent(
            $this,
            new UIData($this->txt('asqt_settings'), $this->renderSettings())
        ));
    }

    private function renderSettings() : string
    {
        $form = $this->createForm();

        return $this->renderKSComponent($form);
    }

    private function createForm() : Standard
    {
        $fields = [];

        foreach ($this->modules as $module) {
            /** @var AbstractObjectFactory $factory */
            $factory = $module->getConfigFactory();
            $fields = array_merge($fields, $factory->getFormfields($this->access->getStorage()->getConfiguration(get_class($module))));
        }

        return $this->getKSFactory()->input()->container()->form()->standard(
            $this->getCommandLink(self::CMD_STORE_SETTINGS),
            $fields
        );
    }

    public function storeSettings() : void
    {
        $form = $this->createForm()->withRequest($this->getRequest());
        $data = $form->getData();

        foreach ($this->modules as $module) {
            /** @var AbstractObjectFactory $factory */
            $factory = $factory = $module->getConfigFactory();
            $config = $factory->readObjectFromPost($data);
            $this->access->getStorage()->setConfiguration(get_class($module), $config);
        }
        $this->access->getStorage()->save();

        $this->raiseEvent(new ForwardToCommandEvent($this, self::CMD_SHOW_SETTINGS));
    }

    public function getCommands() : array
    {
        return [
            self::CMD_SHOW_SETTINGS,
            self::CMD_STORE_SETTINGS
        ];
    }
}