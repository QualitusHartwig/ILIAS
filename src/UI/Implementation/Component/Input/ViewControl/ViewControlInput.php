<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\UI\Implementation\Component\Input\ViewControl;

use ILIAS\UI\Component\Input\Container\ViewControl\ViewControlInput as ViewControlInputInterface;
use ILIAS\UI\Implementation\Component\Input\Input;
use ILIAS\UI\Implementation\Component\Triggerer;
use ILIAS\UI\Implementation\Component\JavaScriptBindable;
use ILIAS\UI\Component\Signal;

abstract class ViewControlInput extends Input implements ViewControlInputInterface
{
    use JavaScriptBindable;
    use Triggerer;

    protected ?Signal $change_signal = null;

    public function withOnChange(Signal $change_signal): self
    {
        $clone = clone $this;
        $clone->change_signal = $change_signal;
        return $clone;
    }

    public function getOnChangeSignal(): Signal
    {
        if (!$this->change_signal) {
            throw new \LogicException('View Control must be inside of View Control Container');
        }
        return $this->change_signal;
    }
}
