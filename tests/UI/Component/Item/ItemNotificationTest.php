<?php

/* Copyright (c) 2019 Timon Amstutz <timon.amstutz@ilub.unibe.ch Extended GPL, see docs/LICENSE */

require_once(__DIR__ . "/../../../../libs/composer/vendor/autoload.php");
require_once(__DIR__ . "/../../Base.php");

use \ILIAS\UI\Implementation as I;

/**
 * Test Notification Items
 */
class ItemNotificationTest extends ILIAS_UI_TestBase
{
    public function setUp() : void
    {
        $this->sig_gen = new I\Component\SignalGenerator();
    }

    public function getIcon(){
        return $this->getUIFactory()->symbol()->icon()->standard("name", "aria_label", "small", false);

    }

    public function getUIFactory()
    {
        $factory = new class extends NoUIFactory {
            public function item()
            {
                return new I\Component\Item\Factory();
            }
            public function Link()
            {
                return new \ILIAS\UI\Implementation\Component\Link\Factory();
            }
            public function button()
            {
                return new I\Component\Button\Factory();
            }
            public function symbol() : ILIAS\UI\Component\Symbol\Factory
            {
                return new I\Component\Symbol\Factory(
                    new I\Component\Symbol\Icon\Factory(),
                    new I\Component\Symbol\Glyph\Factory()
                );
            }
            public function mainControls() : ILIAS\UI\Component\MainControls\Factory
            {
                return new I\Component\MainControls\Factory(
                    $this->sig_gen,
                    new I\Component\MainControls\Slate\Factory(
                        $this->sig_gen
                        , new \ILIAS\UI\Implementation\Component\Counter\Factory())
                );
            }
        };
        $factory->sig_gen = $this->sig_gen;
        return $factory;
    }

    public function test_implements_factory_interface()
    {
        $f = $this->getUIFactory()->item();

        $this->assertInstanceOf("ILIAS\\UI\\Component\\Item\\Notification", $f->notification("title",$this->getIcon()));
    }


    public function test_get_title()
    {
        $f = $this->getUIFactory()->item();
        $c = $f->standard("title");

        $this->assertEquals($c->getTitle(), "title");
    }

    public function test_get_title_as_link()
    {
        $f = $this->getUIFactory()->item();
        $title_link = $this->getUIFactory()->link()->standard("TestLink","");
        $c = $f->standard($title_link,$this->getIcon());

        $this->assertEquals($c->getTitle(), $title_link);
    }


    public function test_with_description()
    {
        $f = $this->getUIFactory()->item();

        $c = $f->notification("title",$this->getIcon())->withDescription("description");

        $this->assertEquals($c->getDescription(), "description");
    }

    public function test_with_properties()
    {
        $f = $this->getUIFactory()->item();

        $props = array("prop1" => "val1", "prop2" => "val2");
        $c = $f->notification("title",$this->getIcon())->withProperties($props);

        $this->assertEquals($c->getProperties(), $props);
    }

    public function test_with_actions()
    {
        $f = $this->getUIFactory()->item();

        $actions = new I\Component\Dropdown\Standard(array(
            new I\Component\Button\Shy("ILIAS", "https://www.ilias.de"),
            new I\Component\Button\Shy("GitHub", "https://www.github.com")
        ));
        $c = $f->notification("title",$this->getIcon())->withActions($actions);

        $this->assertEquals($c->getActions(), $actions);
    }

    public function test_with_lead_icon()
    {
        $f = $this->getUIFactory()->item();

        $c = $f->notification("title",$this->getIcon());
        $this->assertEquals($c->getLeadIcon(), $this->getIcon());
        $icon2 = $this->getIcon()->withIsOutlined(true);

        $this->assertEquals($c->withLeadIcon($icon2)->getLeadIcon(), $icon2);

    }

    public function test_with_close_action()
    {
        $f = $this->getUIFactory()->item();

        $c = $f->notification("title", $this->getIcon())->withCloseAction("closeAction");

        $this->assertEquals($c->getCloseAction(), "closeAction");
    }

    public function test_with_additional_content()
    {
        $f = $this->getUIFactory()->item();

        $content = new I\Component\Legacy\Legacy("someContent");
        $c = $f->notification("title", $this->getIcon())->withAdditionalContent($content);

        $this->assertEquals($c->getAdditionalContent(), $content);
    }

    public function test_with_aggregate_notifications()
    {
        $f = $this->getUIFactory()->item();

        $aggregate = $f->notification("title_aggregate", $this->getIcon());
        $c = $f->notification("title", $this->getIcon())
               ->withAggregateNotifications([$aggregate,$aggregate]);


        $this->assertEquals($c->getAggregateNotifications(), [$aggregate,$aggregate]);
    }

    public function test_render_fully_featured(){
        $f = $this->getUIFactory()->item();
        $r = $this->getDefaultRenderer();

        $props = array("prop1" => "val1", "prop2" => "val2");
        $content = new I\Component\Legacy\Legacy("someContent");
        $actions = new I\Component\Dropdown\Standard(array(
            new I\Component\Button\Shy("ILIAS", "https://www.ilias.de"),
            new I\Component\Button\Shy("GitHub", "https://www.github.com")
        ));
        $title_link = $this->getUIFactory()->link()->standard("TestLink","");
        $aggregate = $f->notification("title_aggregate", $this->getIcon());

        $c = $f->notification($title_link,$this->getIcon())
            ->withDescription("description")
            ->withProperties($props)
            ->withAdditionalContent($content)
            ->withAggregateNotifications([$aggregate])
            ->withCloseAction("closeAction")
            ->withActions($actions)
            ;

        $html = $this->brutallyTrimHTML($r->render($c));
        $expected = <<<EOT
<div class="il-item il-notification-item" id="id_4">
    <div class="media">
        <div class="media-left">
            <div class="icon name small" aria-label="aria_label"></div>
        </div>
        <div class="media-body">
            <h5 class="il-item-notification-title"><a href="" >TestLink</a></h5>
            <button type="button" class="close" data-dismiss="modal" id="id_3"> <span aria-hidden="true">&times;</span> <span class="sr-only">Close</span></button>
            <div class="il-item-description">description</div>
            <div class="dropdown">
                <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> <span class="caret"></span></button>
                <ul class="dropdown-menu">
                    <li>
                        <button class="btn btn-link" data-action="https://www.ilias.de" id="id_1">ILIAS</button>
                    </li>
                    <li>
                        <button class="btn btn-link" data-action="https://www.github.com" id="id_2">GitHub</button>
                    </li>
                </ul>
            </div>
            <div class="il-item-additional-content">someContent</div>
            <hr class="il-item-divider">
            <div class="row">
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-sm-5 il-item-property-name">prop1</div>
                        <div class="col-sm-7 il-item-property-value il-multi-line-cap-3">val1</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-sm-5 il-item-property-name">prop2</div>
                        <div class="col-sm-7 il-item-property-value il-multi-line-cap-3">val2</div>
                    </div>
                </div>
            </div>
            <div class="il-aggregate-notifications" data-aggregatedby="id_4">
                <div class="il-maincontrols-slate il-maincontrols-slate-notification">
                    <div class="il-maincontrols-slate-notification-title">
                        <button class="btn btn-bulky" data-action=""><span class="glyph" aria-label="back"><span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span></span>
                            <div><span class="bulky-label">Back</span></div>
                        </button>
                    </div>
                    <div class="il-maincontrols-slate-content">
                        <div class="il-item il-notification-item">
                            <div class="media">
                                <div class="media-left">
                                    <div class="icon name small" aria-label="aria_label"></div>
                                </div>
                                <div class="media-body">
                                    <h5 class="il-item-notification-title">title_aggregate</h5> </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
EOT;

        $this->assertEquals($this->brutallyTrimHTML($expected), $html);
    }
}
