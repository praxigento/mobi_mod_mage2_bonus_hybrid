<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Calc\Request;

/**
 * @method string getDateApplied() Transaction applied dates for calculation. UTC current date is used if missed.
 * @method void setDateApplied(string $data)
 * @method string getDatePerformed() Operation performed dates for calculation. UTC current date is used if missed.
 * @method void setDatePerformed(string $data)
 * @method string getScheme() Type of the calculation scheme (DEFAULT or EU).
 * @method void setScheme(string $data)
 */
class Base extends \Praxigento\Core\Lib\Service\Base\Request {

}