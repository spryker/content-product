<?php

/**
 * This file is part of the Spryker Platform.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spryker\Client\Payolution;

use Spryker\Client\Kernel\AbstractFactory;
use Spryker\Client\Payolution\Session\PayolutionSession;
use Spryker\Client\Payolution\Zed\PayolutionStub;

class PayolutionFactory extends AbstractFactory
{

    /**
     * @return \Spryker\Client\Payolution\Session\PayolutionSession
     */
    public function createPayolutionSession()
    {
        return new PayolutionSession($this->createSessionClient());
    }

    /**
     * @return \Spryker\Client\Payolution\Zed\PayolutionStubInterface
     */
    public function createPayolutionStub()
    {
        return new PayolutionStub($this->createZedRequestClient());
    }

}
