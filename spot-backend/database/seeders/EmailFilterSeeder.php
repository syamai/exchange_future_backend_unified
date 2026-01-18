<?php

namespace Database\Seeders;

use App\Consts;
use App\Models\ChatbotType;
use App\Models\EmailFilter;
use Illuminate\Database\Seeder;

class EmailFilterSeeder extends Seeder
{
    public function run()
    {
        $whiteList = [
			"gmail.com",
			"yahoo.com",
			"outlook.com",
			"hotmail.com",
			"icloud.com",
			"protonmail.com",
			"zoho.com",
			"aol.com",
			"gmx.com",
			"mail.com",
			"yandex.com",
			"fastmail.com",
			"hey.com",
			"tutanota.com",
			"pm.me",
			"live.com",
			"mac.com",
			"me.com",
			"edu.vn",
			"vnptmail.vn",
			"vnn.vn",
			"viettel.vn",
			"mobiemail.vn",
			"fpt.vn",
			"fpt.com.vn",
			"email.vn",
			"vnpay.vn",
			"vng.com.vn",
			"tiki.vn",
			"shopee.vn",
			"lazada.vn",
			"grab.com",
			"be.com.vn",
			"techcombank.com.vn",
			"vietcombank.com.vn",
			"tpb.vn",
			"vpbank.com.vn",
			"mbbank.com.vn",
			"vib.com.vn",
			"bambooairways.com",
			"vietjetair.com",
			"vietnamairlines.com",
			"vinfastauto.com",
			"vingroup.net",
			"sunhospitality.com",
			"hcmut.edu.vn",
			"hust.edu.vn",
			"fpt.edu.vn",
			"fe.edu.vn",
			"tdtu.edu.vn",
			"ueh.edu.vn",
			"rmit.edu.vn",
			"ptithcm.edu.vn",
			"hcmus.edu.vn",
			"sgu.edu.vn",
			"vanlanguni.vn",
			"qnu.edu.vn",
			"ute.udn.vn",
			"hutech.edu.vn",
			"ntu.edu.vn",
			"icloud.com.vn",
			"tutanota.com",
			"runbox.com",
			"zoho.eu",
			"inbox.lv",
			"orange.fr",
			"naver.com",
			"daum.net",
			"qq.com",
			"126.com",
			"163.com",
			"yeah.net",
			"safe-mail.net",
			"seznam.cz",
			"mail.ru",
			"bk.ru",
			"list.ru",
			"ukr.net",
			"posteo.de",
			"spt.vn",
			"netnam.vn",
			"vietinbank.vn",
			"agribank.com.vn",
			"bidv.com.vn",
			"evn.com.vn",
			"petrovietnam.com.vn",
			"proton.me",
			"mailfence.com",
			"disroot.org",
			"ctmail.com",
			"engineer.com",
			"consultant.com",
			"post.com",
			"usa.com",
			"europe.com",
			"asia.com",
			"gawab.com",
			"teacher.com",
			"mailcity.com",
			"iname.com",
			"hushmail.com",
			"skiff.com",

        ];
        $blackList = [
			"emailclub.net",
			"blueink.top",
			"whyusoserious.org",
			"e-mail.lol",
			"desumail.com",
			"message.rest",
			"300bucks.net",
			"energymail.org",
			"myhyperspace.org",
			"e-boss.xyz",
			"shroudedhills.com",
			"lostspaceship.net",
			"letters.monster",
			"sendme.digital",
			"spacemail.icu",
			"writemeplz.net",
			"wirelicker.com",
			"gopostal.top",
			"mailgod.xyz",
			"postalbro.com",
			"homingpigeon.org",
			"electroletter.space",
			"guesswho.click",
			"rocketpost.org",
			"mypost.lol",
			"specialmail.online",
			"2mails1box.com",
			"anymail.xyz",
			"ultramail.pro",
			"echat.rest",
			"gogomail.ink",
			"gufum.com",
			"gmaio.com",
			"gmail.con",
			"gamil.com",
			"gmsil.com",
			"protonbox.pro",
		];

        foreach ($whiteList as $domain) {
            EmailFilter::updateOrCreate(
            	['domain' => $domain],
				[
					'type' => Consts::TYPE_WHITELIST,
					'admin_id' => 1
				]
			);
        }

		foreach ($blackList as $domain) {
			EmailFilter::updateOrCreate(
				['domain' => $domain],
				[
					'type' => Consts::TYPE_BLACKLIST,
					'admin_id' => 1
				]
			);
		}
    }
} 