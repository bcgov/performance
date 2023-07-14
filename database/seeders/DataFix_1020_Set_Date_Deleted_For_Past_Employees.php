<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmployeeDemo;
use App\Models\User;
use App\Models\UserReportingTo;
use App\Models\SharedProfile;
use App\Models\PreferredSupervisor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Console\Command;



class DataFix_1020_Set_Date_Deleted_For_Past_Employees extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Log::info(Carbon::now()." - ZenHub #1020 - Start");

        $past_array = [
            '000210',
            '000288',
            '000291',
            '000435',
            '000819',
            '001267',
            '002482',
            '003104',
            '005011',
            '005346',
            '005946',
            '006772',
            '006824',
            '009315',
            '009389',
            '009446',
            '009788',
            '010991',
            '012384',
            '013150',
            '013899',
            '014194',
            '015246',
            '015546',
            '015904',
            '016514',
            '016864',
            '017886',
            '018012',
            '018368',
            '018603',
            '018675',
            '019345',
            '019457',
            '020436',
            '020474',
            '020815',
            '021058',
            '021360',
            '021688',
            '022109',
            '022357',
            '022374',
            '022846',
            '023577',
            '024422',
            '024453',
            '024486',
            '025078',
            '025157',
            '026077',
            '027661',
            '028270',
            '028724',
            '029665',
            '042193',
            '048561',
            '048716',
            '048732',
            '048782',
            '052132',
            '052207',
            '052719',
            '053435',
            '053899',
            '054046',
            '054950',
            '057629',
            '059988',
            '060494',
            '060748',
            '061202',
            '062054',
            '062311',
            '062399',
            '062432',
            '063299',
            '063630',
            '063746',
            '064433',
            '064440',
            '064677',
            '066440',
            '067513',
            '068981',
            '069024',
            '071204',
            '075153',
            '075725',
            '076050',
            '081779',
            '085474',
            '097024',
            '097160',
            '097756',
            '097881',
            '098450',
            '098526',
            '098544',
            '099464',
            '099477',
            '099703',
            '099906',
            '100595',
            '100807',
            '102406',
            '102798',
            '103841',
            '104247',
            '104927',
            '104948',
            '107157',
            '108035',
            '108223',
            '108501',
            '108637',
            '108797',
            '110314',
            '110669',
            '110678',
            '110839',
            '111157',
            '111298',
            '111905',
            '112109',
            '112152',
            '112892',
            '112963',
            '114369',
            '115473',
            '116875',
            '116897',
            '117063',
            '117404',
            '118435',
            '119275',
            '119429',
            '119735',
            '120363',
            '121545',
            '121574',
            '121610',
            '124304',
            '124421',
            '124905',
            '124950',
            '125447',
            '125514',
            '125647',
            '125970',
            '126139',
            '126197',
            '127270',
            '127456',
            '128011',
            '129231',
            '130129',
            '131146',
            '131163',
            '131247',
            '131458',
            '131527',
            '131621',
            '131626',
            '131929',
            '131942',
            '132109',
            '133177',
            '133389',
            '133512',
            '133513',
            '133523',
            '134065',
            '134630',
            '135240',
            '135570',
            '135738',
            '135778',
            '136179',
            '136697',
            '136809',
            '137114',
            '137586',
            '139014',
            '139043',
            '139494',
            '139575',
            '139653',
            '140275',
            '141501',
            '141533',
            '141983',
            '142118',
            '142275',
            '142523',
            '142849',
            '143335',
            '143487',
            '143713',
            '143913',
            '144786',
            '144881',
            '144898',
            '145028',
            '145377',
            '146049',
            '146211',
            '146538',
            '146586',
            '146850',
            '146930',
            '146988',
            '147179',
            '147552',
            '147771',
            '147781',
            '147958',
            '148372',
            '148831',
            '148932',
            '149006',
            '149160',
            '149396',
            '149490',
            '150037',
            '150141',
            '151795',
            '151824',
            '151911',
            '152547',
            '152575',
            '152611',
            '152772',
            '152916',
            '153014',
            '153280',
            '153281',
            '153414',
            '153664',
            '153696',
            '153861',
            '153919',
            '154029',
            '154528',
            '155469',
            '155563',
            '155624',
            '155706',
            '155773',
            '155896',
            '155993',
            '156113',
            '156212',
            '156305',
            '156382',
            '156520',
            '156728',
            '156754',
            '157175',
            '157510',
            '157547',
            '157776',
            '157876',
            '158283',
            '158289',
            '158512',
            '159017',
            '159327',
            '159604',
            '159680',
            '159748',
            '160372',
            '160428',
            '160451',
            '160455',
            '160466',
            '160676',
            '160710',
            '160777',
            '160926',
            '161070',
            '161159',
            '161363',
            '161380',
            '161636',
            '161877',
            '161956',
            '162199',
            '162292',
            '162324',
            '162424',
            '162630',
            '162675',
            '162852',
            '162938',
            '162954',
            '163184',
            '163323',
            '163586',
            '163782',
            '163797',
            '164177',
            '164181',
            '164398',
            '164477',
            '164481',
            '164515',
            '164630',
            '164792',
            '164880',
            '164917',
            '165053',
            '165229',
            '165488',
            '165864',
            '165986',
            '165988',
            '166200',
            '166450',
            '166665',
            '166680',
            '166917',
            '166972',
            '167115',
            '167136',
            '167141',
            '167158',
            '167169',
            '167324',
            '167456',
            '167510',
            '167576',
            '167587',
            '167628',
            '167631',
            '167862',
            '168115',
            '168252',
            '168284',
            '168376',
            '168427',
            '168458',
            '168471',
            '168515',
            '168588',
            '168884',
            '168903',
            '168971',
            '169069',
            '169201',
            '169285',
            '169314',
            '169355',
            '169448',
            '169481',
            '169543',
            '169687',
            '169975',
            '170013',
            '170251',
            '170636',
            '170747',
            '170912',
            '171001',
            '171193',
            '171411',
            '171568',
            '171618',
            '171622',
            '171702',
            '171718',
            '171719',
            '171827',
            '171831',
            '171845',
            '171854',
            '172032',
            '172206',
            '172499',
            '172885',
            '172984',
            '173165',
            '173174',
            '173327',
            '173559',
            '173745',
            '173850',
            '173958',
            '174098',
            '174110',
            '174442',
            '174593',
            '174616',
            '174634',
            '174663',
            '174784',
            '174872',
            '175025',
            '175149',
            '175156',
            '175168',
            '175290',
            '175435',
            '175529',
            '175620',
            '175671',
            '175843',
            '175853',
            '175887',
            '175930',
            '176000',
            '176017',
            '176046',
            '176047',
            '176086',
            '176106',
            '176186',
            '176208',
            '176271',
            '176274',
            '176470',
            '176828',
            '176881',
            '176908',
            '176940',
            '176983',
            '177135',
            '177150',
            '177188',
            '177207',
            '177211',
            '177235',
            '177267',
            '177350',
            '177363',
            '177450',
            '177542',
            '177607',
            '177617',
            '177680',
            '177705',
            '177822',
            '177865',
            '177926',
            '178199',
            '178240',
            '178283',
            '178302',
            '178304',
            '178354',
            '178435',
            '178546',
            '178578',
            '178602',
            '178603',
            '178610',
            '178664',
            '178726',
            '178845',
            '178857',
            '178872',
            '178894',
            '178924',
            '178928',
            '178970',
            '179117',
            '179144',
            '179249',
            '179348',
            '179358',
            '179373',
            '179374',
            '179515',
            '179557',
            '179597',
            '179893',
            '179901',
            '179918',
            '179958',
            '180067',
            '180106',
            '180162',
            '180231',
            '180338',
            '180377',
            '180481',
            '180504',
            '180550',
            '180575',
            '180707',
            '180739',
            '180808',
            '180921',
            '180922',
            '181116',
            '181230',
            '181349',
            '181405',
            '181440',
            '181441',
            '181444',
            '181556',
            '181568',
            '181621',
            '181656',
            '181723',
            '181778',
            '181813',
            '181825',
            '181935',
            '181999',
            '182001',
            '182050',
            '182116',
            '182120',
            '182123',
            '182141',
            '182150',
            '182159',
            '182177',
            '182274',
            '182333',
            '182334',
            '182339',
            '182380',
            '182385',
            '182436',
            '182518',
            '182544',
            '182667',
            '182675',
            '182852',
            '182883',
            '182905',
            '182917',
            '182931',
            '182951',
            '182995',
            '183012',
            '183144',
            '183152',
            '183213',
            '183238',
            '183357',
            '183641',
            '183668',
            '183713',
            '183756',
            '183856',
            '183857',
            '183888',
            '183923',
            '184014',
            '184090',
            '184153',
            '184245',
            '184395',
            '184442',
            '184532',
            '184642',
            '184666',
            '184883',
            '184973',
            '185056',
            '185146',
            '185189',
            '185190',
            '185364',
            '185366',
            '185390',
            '185631',
            '185741',
            '185785',
            '185852',
            '185854',
            '185894',
            '185897',
            '185900',
            '185950',
            '185951',
            '186065',
            '186101',
            '186114',
            '186122',
            '186123',
            '186236',
            '186260',
            '186288',
            '186291',
            '186314',
            '186378',
            '186420',
            '186480',
            '186481',
            '186486',
            '186501',
            '186502',
            '186529',
            '186531',
            '186543',
            '186548',
            '186549',
            '186583',
            '186617',
            '186621',
            '186622',
            '186751',
            '186764',
            '186770',
            '186794',
            '186799',
            '186814',
            '186856',
            '186933',
            '187060',
            '187087',
            '187166',
            '187170',
            '187183',
            '187215',
            '187257',
            '187331',
            '187480',
            '187523',
            '187591',
            '187679',
            '187851',
            '187876',
            '187969',
            '188022',
            '188024',
            '188029',
            '188341',
            '188457',
            '188519',
            '188754',
        ];

        Log::info(Carbon::now()." - Create array of user IDs");
        $past_profiles = User::whereIn('employee_id', $past_array)
            ->distinct()
            ->orderBy('id')
            ->get();

        foreach($past_profiles AS $prof) {

            $all_reportto = UserReportingTo::where('reporting_to_id', $prof->id)
                ->get();
            foreach($all_reportto AS $item) {
                Log::info(Carbon::now()." - Deleting from user_reporting_tos - {$prof->employee_id} - {$item->reporting_to_id} / {$item->user_id}");
                $item->delete();
            }

            $all_shared = SharedProfile::where('shared_with', $prof->id)
                ->get();
            foreach($all_shared AS $item) {
                Log::info(Carbon::now()." - Deleting from sahred_profiles - {$prof->employee_id} - {$item->shared_with} / {$item->shared_id} / {$item->shared_by}");
                $item->delete();
            }

            $changed = false;
            if(!$prof->acctlock) {
                Log::info(Carbon::now()." - Updating acctlock in users - {$prof->employee_id} - acctlock={$prof->acctlock}");
                $prof->acctlock = 1;
                $changed = true;
            } 
            if($prof->reporting_to) {
                Log::info(Carbon::now()." - Updating blank to reporting_to in users - {$prof->employee_id} - reporting_to={$prof->reporting_to}");
                $prof->reporting_to = null;
                $changed = true;
            }
            if($changed) {
                $prof->save();
            }

        }

        $all_demo = EmployeeDemo::whereIn('employee_id', $past_array)
            ->orderBy('employee_id')
            ->orderBy('empl_record')
            ->get();
        foreach($all_demo AS $demo) {

            $prefers = PreferredSupervisor::where('supv_empl_id', $demo->employee_id)
                ->get();
            foreach($prefers AS $prefer) {
                Log::info(Carbon::now()." - Deleting from preferred_supervisor - {$demo->employee_id} - {$prefer->supv_empl_id} / {$prefer->employee_id} / {$prefer->position_nbr}");
                $prefer->delete();
            }

            if($demo->date_deleted) {
                Log::info(Carbon::now()." - Skipping employee_demo update, date_deleted is NOT blank - {$demo->employee_id} / {$demo->empl_record} - date_deleted={$demo->date_deleted}");
            } else {
                Log::info(Carbon::now()." - Updating blank date_deleted in employee_demo - {$demo->employee_id} / {$demo->empl_record} - date_deleted=2023-07-01");
                EmployeeDemo::where('employee_id', $demo->employee_id)
                    ->where('empl_record', $demo->empl_record)
                    ->update(['date_deleted' => '2023-07-01']);
            }

        }
    
        Log::info(Carbon::now()." - ZenHub #1020 - End");

    }
}