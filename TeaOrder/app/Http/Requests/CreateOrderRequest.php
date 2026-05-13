<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_sku_id' => 'required|integer|exists:product_skus,id',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'activity_id' => 'nullable|integer|exists:activities,id',
            'remark' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => '请选择顾客',
            'customer_id.integer' => '顾客ID格式错误',
            'customer_id.exists' => '顾客不存在',
            'items.required' => '请添加商品',
            'items.array' => '商品列表格式错误',
            'items.min' => '至少需要1个商品',
            'items.*.product_sku_id.required' => '第:position个商品缺少SKU ID',
            'items.*.product_sku_id.integer' => 'SKU ID必须是数字',
            'items.*.product_sku_id.exists' => '商品不存在',
            'items.*.quantity.required' => '第:position个商品缺少数量',
            'items.*.quantity.integer' => '数量必须是整数',
            'items.*.quantity.min' => '数量不能小于1',
            'items.*.quantity.max' => '数量不能超过99',
            'activity_id.integer' => '活动ID格式错误',
            'activity_id.exists' => '活动不存在或已结束',
            'remark.string' => '备注格式错误',
            'remark.max' => '备注最多500字',
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_id' => '顾客ID',
            'items' => '商品列表',
            'items.*.product_sku_id' => '商品SKU ID',
            'items.*.quantity' => '购买数量',
            'activity_id' => '活动ID',
            'remark' => '订单备注',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->toArray();

        $formattedErrors = [];
        foreach ($errors as $field => $messages) {
            $formattedErrors[] = [
                'field' => $field,
                'message' => $messages[0],
            ];
        }

        throw new ValidationException(
            $validator,
            response()->json([
                'code' => 400,
                'message' => '参数错误',
                'data' => null,
                'errors' => $formattedErrors,
            ], 400)
        );
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('items');

            // 如果 items 存在且是数组，进行深度验证
            if (is_array($items)) {
                $this->validateItemsDeeply($validator, $items);
            }
        });
    }

    private function validateItemsDeeply(Validator $validator, array $items): void
    {
        foreach ($items as $index => $item) {
            $position = $index + 1;

            if (!is_array($item)) {
                $validator->errors()->add(
                    "items.{$index}",
                    "第{$position}个商品格式错误"
                );
                continue;
            }

            if (!isset($item['product_sku_id']) || $item['product_sku_id'] === '' || $item['product_sku_id'] === null) {
                $validator->errors()->add(
                    "items.{$index}.product_sku_id",
                    "第{$position}个商品缺少SKU ID"
                );
            }

            if (!isset($item['quantity']) || $item['quantity'] === '' || $item['quantity'] === null) {
                $validator->errors()->add(
                    "items.{$index}.quantity",
                    "第{$position}个商品缺少数量"
                );
            } elseif (!is_numeric($item['quantity'])) {
                $validator->errors()->add(
                    "items.{$index}.quantity",
                    "数量必须为数字"
                );
            } elseif ($item['quantity'] < 1) {
                $validator->errors()->add(
                    "items.{$index}.quantity",
                    "数量不能小于1"
                );
            } elseif ($item['quantity'] > 99) {
                $validator->errors()->add(
                    "items.{$index}.quantity",
                    "数量不能超过99"
                );
            }
        }
    }
}
