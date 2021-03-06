<?php
/**
 * Created by PhpStorm.
 * User: 简美
 * Date: 2020/4/10
 * Time: 10:32
 */

namespace App\Services;

use App\Event\PaySuccessEvent;
use App\Event\RefundSuccessEvent;
use App\Exception\ServiceException;
use App\Handler\Pay\PayFactory;
use App\Model\Order;
use Carbon\Carbon;
use Hyperf\Di\Annotation\Inject;
use Phper666\JwtAuth\Jwt;
use Psr\EventDispatcher\EventDispatcherInterface;

class AliPayService
{
    private $pay;

    /**
     * @Inject()
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @Inject()
     * @var Jwt
     */
    private $jwt;

    public function __construct(PayFactory $factory)
    {
        $this->pay = $factory->get('alipay');
    }

    /***
     * 支付宝网页支付
     * @param $orderId
     * @return mixed
     */
    public function aliPayWeb($orderId)
    {
        $order = Order::getFirstById($orderId);

        if (!$order || $order->user_id != authUser()->id)
        {
            throw new ServiceException(403, '订单不存在');
        }

        if ($order->paid_at || $order->closed)
        {
            throw new ServiceException(403, '订单已经关闭');
        }

        return $this->pay->webPay($order->no, $order->total_amount, $order->no)->getContent();
    }

    /**
     * 检验前端支付参数
     * @return array
     */
    public function aliPayWebReturn($data)
    {
        return $this->pay->verify($data);
    }

    /**
     * 支付成功服务器回调
     * @param $data
     */
    public function aliPayNotify($data)
    {
        $no = $data['out_trade_no'];

        $order = Order::getFirstByWhere(['no' => $no]);
        if (!$order)
        {
            //DoSomeThing
            return;
        }

        if ($order->paid_at)
        {
            $this->pay->success();
        }

        $order->update([
            'paid_at' => Carbon::now(), // 支付时间
            'payment_method' => 'alipay', // 支付方式
            'payment_no' => $data['trade_no'], // 支付宝订单号
        ]);

        $this->pay->success();
        $this->eventDispatcher->dispatch(new PaySuccessEvent($order));
    }

    /**
     * 支付宝退款
     * @param Order $order *订单
     */
    public function refund(Order $order)
    {
        $refundNo = getUUID('refund');
        $result = $this->pay->refund($order->no, $order->total_amount, $refundNo);

        if ($result->sub_code)
        {
            // 将退款失败的保存存入 extra 字段
            $extra = $order->extra;
            $extra['refund_failed_code'] = $result->sub_code;
            // 将订单的退款状态标记为退款失败
            $order->update([
                'refund_no' => $refundNo,
                'refund_status' => Order::REFUND_STATUS_FAILED,
                'extra' => $extra,
            ]);
        }
        else
        {
            // 将订单的退款状态标记为退款成功并保存退款订单号
            $order->update([
                'refund_no' => $refundNo,
                'refund_status' => Order::REFUND_STATUS_SUCCESS,
            ]);
            //触发退款成功事件
            $this->eventDispatcher->dispatch(new RefundSuccessEvent($order));
        }
    }
}