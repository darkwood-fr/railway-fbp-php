<?php

declare(strict_types=1);

namespace RFBP\Test\Rail;

use ArrayObject;
use RFBP\Driver\AmpDriver;
use RFBP\Driver\ReactDriver;
use RFBP\Driver\SwooleDriver;
use RFBP\DriverInterface;
use RFBP\Ip;
use RFBP\Rail\ParallelRail;
use RFBP\Rail\Rail;
use RFBP\Rail\SequenceRail;
use RuntimeException;
use function Amp\delay;

class ParallelRailTest extends AbstractRailTest
{
    /**
     * @dataProvider jobProvider
     */
    public function testJob(DriverInterface $driver): void
    {
        $ip1 = new Ip(new ArrayObject(['wait' => 10, 'n1' => 3, 'n2' => 4, 'id' => 1]));
        $ip2 = new Ip(new ArrayObject(['wait' => 1, 'n1' => 2, 'n2' => 5, 'id' => 2]));

        $jobs = [function (object $data) {
            $data['n1'] *= 2;
        }, function (object $data) {
            $data['n2'] *= 4;
        }];
        $rail = new ParallelRail($jobs, null, $driver);

        $ips = [];
        $rail->pipe(function (Ip $ip) use ($driver, &$ips, $ip1, $ip2) {
            $ips[] = $ip;
            if(count($ips) === 2) {
                $this->assertSame($ip1, $ips[0]);
                $this->assertSame($ip2, $ips[1]);
                self::assertSame(6, $ip1->getData()['n1']);
                self::assertSame(16, $ip1->getData()['n2']);
                self::assertSame(4, $ip2->getData()['n1']);
                self::assertSame(20, $ip2->getData()['n2']);

                $driver->stop();
            }
        });

        ($rail)($ip1);
        ($rail)($ip2);

        $driver->run();
    }

    /**
     * @return array<array>
     */
    public function jobProvider(): array
    {
        return [
            [new AmpDriver()],
            [new ReactDriver()],
            [new SwooleDriver()],
        ];
    }
}
