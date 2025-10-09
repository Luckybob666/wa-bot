<?php

namespace App\Services;

use App\Models\BatchComparison;
use App\Models\Group;
use App\Models\PhoneBatch;
use App\Models\PhoneBatchNumber;

class ComparisonService
{
    /**
     * 执行号码比对
     */
    public function compare(PhoneBatch $batch, Group $group): BatchComparison
    {
        // 创建比对记录
        $comparison = BatchComparison::create([
            'phone_batch_id' => $batch->id,
            'group_id' => $group->id,
            'status' => BatchComparison::STATUS_PROCESSING,
        ]);

        try {
            // 获取批次中的所有手机号（从明细表）
            $batchNumbers = $batch->phoneNumbers()->pluck('phone_number')->toArray();
            
            // 获取群组中的所有用户手机号
            $groupNumbers = $group->whatsappUsers()
                ->whereNotNull('phone_number')
                ->pluck('phone_number')
                ->map(function ($phone) {
                    // 清理手机号格式
                    return preg_replace('/[^0-9]/', '', $phone);
                })
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // 比对结果
            $matchedNumbers = []; // 批次中已进群的号码
            $unmatchedNumbers = []; // 批次中未进群的号码
            $extraNumbers = []; // 群里多出的号码（不在批次中）

            // 遍历批次号码，检查是否在群里
            foreach ($batchNumbers as $batchNumber) {
                if (in_array($batchNumber, $groupNumbers)) {
                    $matchedNumbers[] = $batchNumber;
                } else {
                    $unmatchedNumbers[] = $batchNumber;
                }
            }

            // 遍历群号码，找出不在批次中的号码
            foreach ($groupNumbers as $groupNumber) {
                if (!in_array($groupNumber, $batchNumbers)) {
                    $extraNumbers[] = $groupNumber;
                }
            }

            // 保存比对结果
            $comparison->setComparisonResults($matchedNumbers, $unmatchedNumbers, $extraNumbers);
            $comparison->save();

            return $comparison;
        } catch (\Exception $e) {
            $comparison->markAsFailed();
            throw $e;
        }
    }

    /**
     * 批量比对（针对多个群组）
     */
    public function batchCompare(PhoneBatch $batch, array $groupIds): array
    {
        $results = [];
        
        foreach ($groupIds as $groupId) {
            $group = Group::find($groupId);
            if ($group) {
                $results[] = $this->compare($batch, $group);
            }
        }
        
        return $results;
    }
}

