<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\AboutUsGeneral;
use App\Models\Addon\Product\Product;
use App\Models\Assignment;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\City;
use App\Models\ClientLogo;
use App\Models\ContactUs;
use App\Models\ContactUsIssue;
use App\Models\Country;
use App\Models\Course;
use App\Models\Course_lecture;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\FaqQuestion;
use App\Models\Home;
use App\Models\Instructor;
use App\Models\InstructorSupport;
use App\Models\Organization;
use App\Models\OurHistory;
use App\Models\Package;
use App\Models\Policy;
use App\Models\RankingLevel;
use App\Models\Review;
use App\Models\State;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserPackage;
use App\Rules\ReCaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;


class MainIndexController extends Controller
{

    public function index()
    {
        if (file_exists(storage_path('installed'))) {
            $data['pageTitle'] = "Home";
            $data['metaData'] = staticMeta(1);
            $data['home'] = Home::first();
            $data['topCourse'] = Enrollment::query()
                ->whereMonth('created_at', now()->month)
                ->select('course_id', DB::raw('count(*) as total'))
                ->groupBy('course_id')
                ->limit(10)
                ->orderBy('total','desc')
                ->get()
                ->pluck('course_id')
                ->toArray();

            if($data['home']->category_courses_area == 1){
                $data['featureCategories'] = Category::with('activeCourses')->with('courses.reviews')->with('courses.user')->with('courses.promotionCourse.promotion')->with('courses.instructor.ranking_level')->with('courses.specialPromotionTagCourse.specialPromotionTag')->active()->feature()->get()->map(function ($q) {
                    $q->setRelation('courses', $q->courses->where('status', 1)->take(12));
                    return $q;
                });
            }

            if($data['home']->top_category_area == 1){
                $data['firstFourCategories'] = Category::with('courses')->feature()->active()->take(4)->get();
            }


            if($data['home']->instructor_support_area == 1){
                $data['aboutUsGeneral'] = AboutUsGeneral::first();
                $data['instructorSupports'] = InstructorSupport::all();
                $data['clients'] = ClientLogo::all();
            }

            if($data['home']->faq_area == 1){
                $data['faqQuestions'] = FaqQuestion::take(6)->get();
            }


            if($data['home']->instructor_area == 1){
                $data['instructors'] = User::query()
                ->leftJoin('instructors as ins', 'ins.user_id', '=', 'users.id')
                ->leftJoin('organizations as org', 'org.user_id', '=', 'users.id')
                ->whereIn('users.role', [USER_ROLE_INSTRUCTOR,USER_ROLE_ORGANIZATION])
                ->where(function($q){
                    $q->where('ins.status', STATUS_APPROVED)
                    ->orWhere('org.status', STATUS_APPROVED);
                })
                ->with('badges')
                ->select('users.*', 'ins.organization_id', DB::raw(selectStatement()))
                ->paginate(5);
            }

            if($data['home']->bundle_area == 1){
                $data['bundles'] = Bundle::with('bundleCourses')->with('user.instructor.ranking_level')->active()->latest()->take(12)->get();
            }

            if($data['home']->consultation_area == 1){
                $data['consultationInstructors'] = User::query()
                ->leftJoin('instructors as ins', 'ins.user_id', '=', 'users.id')
                ->leftJoin('organizations as org', 'org.user_id', '=', 'users.id')
                ->whereIn('users.role', [USER_ROLE_INSTRUCTOR,USER_ROLE_ORGANIZATION])
                ->where(function($q){
                    $q->where('ins.status', STATUS_APPROVED)
                    ->orWhere('org.status', STATUS_APPROVED);
                })
                ->where(function($q){
                    $q->where('ins.consultation_available', STATUS_APPROVED)
                    ->orWhere('org.consultation_available', STATUS_APPROVED);
                })
                ->with('badges')
                ->select('users.*', 'ins.organization_id', DB::raw(selectStatement()))
                ->limit(8)
                ->get();
            }

            if($data['home']->courses_area == 1){
                $data['featuredCourses'] = Course::with('reviews')->with('user')->with('promotionCourse.promotion')->with('instructor.ranking_level')->with('specialPromotionTagCourse.specialPromotionTag')->featured()->active()->take(12)->get();
            }
            if($data['home']->upcoming_courses_area == 1){
                $data['upcomingCourses'] = Course::with('reviews')->with('user')->with('promotionCourse.promotion')->with('instructor.ranking_level')->with('specialPromotionTagCourse.specialPromotionTag')->upcoming()->take(12)->get();
            }

            if(isAddonInstalled('LMSZAIPRODUCT')){
                if($data['home']->product_area == 1){
                    $data['products'] = Product::with('reviews')->where('status', STATUS_SUCCESS)->where('is_feature', STATUS_SUCCESS)->take(12)->get();
                }
            }

            $data['currencyPlacement'] = get_currency_placement();
            $data['currencySymbol'] = get_currency_symbol();
            $packages = Package::where('status', PACKAGE_STATUS_ACTIVE)->where('in_home', PACKAGE_STATUS_ACTIVE)->orderBy('order', 'ASC')->get();
            $data['subscriptions'] = $packages->where('package_type', PACKAGE_TYPE_SUBSCRIPTION);
            $data['instructorSaas'] = $packages->where('package_type', PACKAGE_TYPE_SAAS_INSTRUCTOR);
            $data['organizationSaas'] = $packages->where('package_type', PACKAGE_TYPE_SAAS_ORGANIZATION);

            if($data['home']->subscription_show == 1){
                $data['mySubscriptionPackage'] = UserPackage::where('user_packages.user_id', auth()->id())->where('user_packages.status', PACKAGE_STATUS_ACTIVE)->whereDate('enroll_date', '<=', now())->whereDate('expired_date', '>=', now())->where('package_type', PACKAGE_TYPE_SUBSCRIPTION)->join('packages', 'packages.id', '=', 'user_packages.package_id')->select('package_id', 'package_type', 'subscription_type')->first();
            }
            if($data['home']->saas_show == 1){
                $data['mySaasPackage'] = UserPackage::where('user_packages.user_id', auth()->id())->where('user_packages.status', PACKAGE_STATUS_ACTIVE)->whereDate('enroll_date', '<=', now())->whereDate('expired_date', '>=', now())->whereIn('package_type', [PACKAGE_TYPE_SAAS_INSTRUCTOR, PACKAGE_TYPE_SAAS_ORGANIZATION])->join('packages', 'packages.id', '=', 'user_packages.package_id')->select('package_id', 'package_type', 'subscription_type')->first();
            }
            return view(getThemePath().'.home.home', $data);
        } else {
            return redirect()->to('/install');
        }
    }

}