<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\BattleGroup;
use App\Models\BattleGroupCard;
use App\Models\Card;
use App\Models\Contract;
use App\Models\User;
use App\Notifications\BattleGroupCreationNotification;
use Illuminate\Support\Facades\Request;
use Log;
use Notification;
use RpcServer\BattleInfo;
use RpcServer\CardInfo;

class ContractController extends Controller
{
    public function contractAddressIngest()
    {
        if (env('APP_DEBUG') != 'true') {
            //this feature is only really for the development workflow, so no auth needed.
            return response()->build(self::RESPONSE_MESSAGE_ERROR_UNAUTHORIZED);
        }

        $data = json_decode(Request::getContent(), true);
        Log::info('ingested contract addresses');
        Log::info($data);

        foreach ($data as $contractName => $v) {
            $address = $v['address'];
            Log::info($contractName.' @ '.$address);
            Contract::updateAddress($contractName, $address, $v['transactionHash']);
        }

        return response()->build(self::RESPONSE_MESSAGE_SUCCESS);
    }

    public function getContractAddresses()
    {
        $c = Contract::all()->keyBy(Contract::FIELD_NAME);

        return response()->build(self::RESPONSE_MESSAGE_SUCCESS, $c);
    }

    /*
     * Save the results of a BattleGroup contract Event into the DB.
     */
    public static function processNewBattleGroupEvent($ownerAddress, $battleGroupId, $cards)
    {
        $user = User::getByAddress($ownerAddress);
        $bgData = ['user_id'=>$user->id, 'token_id'=> $battleGroupId];
        $bg = BattleGroup::firstOrCreate($bgData);
        foreach ($cards as $c) {
            $card = Card::getByTokenId($c);
            BattleGroupCard::firstOrCreate(['group_id'=>$bg->id, 'card_id'=>$card->id]);
        }
        if ($bg->wasRecentlyCreated) {
            Log::info('ingested new BattleGroup: user_id', ['data'=>$bgData, 'cardIds'=>$cards]);
        }

        Notification::send($user, new BattleGroupCreationNotification($bg->fresh()));
    }

    public static function processBattleInfoRpc(BattleInfo $bi)
    {
        self::processNewBattleCompletionEvent($bi->getId(), $bi->getWinnerGroupId(), $bi->getLoserGroupId());
    }

    public static function processNewBattleCompletionEvent($battleTokenId, $battleGroupWinnerTokenId, $battleGroupLoserTokenId)
    {
        $battle = Battle::firstOrNew(['token_id' => $battleTokenId]);
        $winner = BattleGroup::getByTokenId($battleGroupWinnerTokenId);
        $loser = BattleGroup::getByTokenId($battleGroupLoserTokenId);

        if ($battle->wasRecentlyCreated) {
            Log::info("ingesting new Battle: $battleTokenId");
        }

        $battle->group_1 = $winner->id;
        $battle->group_winner = $winner->id;
        $battle->group_2 = $loser->id;
        $battle->save();
    }

    public static function processCardInfoRpc(CardInfo $ci)
    {
        self::processNewCardEvent($ci->getOwnerAddress(), $ci->getId(), $ci->getCreationBattleID(), $ci->getOwnerAddress());
    }

    /*
     * Save a new card from the blockchain to the DB
     * TODO: persist name, creation battle id, attributes
     */
    public static function processNewCardEvent($ownerAddress, $tokenId, $creationBattleId, $attributes)
    {
        $user = User::getByAddress($ownerAddress);
        $card = Card::getByTokenId($tokenId);
        $card->user_id = $user->id;
        $card->save();
        if ($card->wasRecentlyCreated) {
            Log::info('ingest new Card: token_id is '.$tokenId);
        }
    }
}
