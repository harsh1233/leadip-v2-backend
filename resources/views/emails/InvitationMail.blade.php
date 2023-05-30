<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <title>Invite Team Member</title>
    <style>
        .emaildata-p1 {
            font-family: 'Montserrat', sans-serif!important;
            color: rgba(51, 51, 51, 0.9)!important;
            line-height: 34px!important;
            font-size: 14px!important;
            margin: 28px!important;
            font-weight: 500!important;
            padding: 0px 38px 0 0px!important;

        }

        .emaildata-p {
            font-family: 'Montserrat', sans-serif!important;
            color: rgba(51, 51, 51, 0.9)!important;
            line-height: 34px!important;
            font-size: 14px!important;
            margin: 28px!important;
            font-weight: 500!important;
            padding: 0px 38px 0 0px!important;

        }

        .divaider {
            border: 2px solid #F3F3F3!important;
            margin: 17px 44px 23px 44px!important;

        }

        .social_btn {
            width: 50px!important;
            height: 50px!important;
            border: none!important;
            background-color: #336FB3!important;
            border-radius: 50%!important;
            margin: 10px 8px!important;
            padding: 0!important;
        }

        .sociallinks {
            margin: 20px!important;
            text-align: center!important;
        }

        span {
            margin-right: 10px!important;
        }
    </style>
</head>

<body>
    <div>
        <div class="container">
            <div class="row mt-5">
                <!--  -->
                <div class="col-lg-6 p-0" style=" box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19); border-radius: 15px;">
                    <img src="{{ env('APP_URL') }}/image/image226.png" alt="logo" style="margin: 0 auto; padding: 30px 38px 0px 59px;  width:100%">
                    <div>
                        <div class="divaider">
                        </div>
                        <div style="padding-left: 59px;">

                            <p class="emaildata-p" style="font-family: 'Montserrat', sans-serif;">
                                Hi {{ $invitedUser->first_name . ' ' . $invitedUser->last_name }}, <br>

                                Welcome to Lead IP! <br>

                                {{ $authUser->first_name }} has invited you to contribute to
                                {{ $authUser->company->name }}.
                                Please accept the invitation for the successful login to contribute to their network
                            </p>
                            <div>
                                <a href="{{ route('create-password', 'token=' . $token . '&email=' . $invitedUser->email . '&fname=' . $invitedUser->first_name . '&lname=' . $invitedUser->last_name) }}">
                                    <a href="{{ url(env('WEBAPP_URL') . config('constants.CREATE_PASSWORD_URL') . '?token=' . $token . '&email=' . $invitedUser->email . '&fname=' . $invitedUser->first_name . '&lname=' . $invitedUser->last_name) }}">
                                        <button style="background: linear-gradient(93.8deg, #386FB3 0%, #59A7DC 101.92%);
                    font-size: 14px; font-weight: 500; cursor: pointer;
                    border-radius: 15px; margin-left:15px ;  border: none; padding: 12px 35px;color: #ffffff;">Accept
                                            Invitation</button>

                                    </a>
                            </div>
                            <p class="emaildata-p1" style="font-family: 'Montserrat', sans-serif;">
                                Please, feel free to reach us out if you need anything! <br>

                                Best regards,<br>

                                Lead IP Team
                            </p>
                        </div>
                        <footer style="background-color: #336FB3;padding: 20px; margin-top: 48px;  border-radius: 0px 0px 15px 15px; " class="d-flex justify-content-center">
                            <div>
                                <!-- <img src="./image/image 305.svg" alt="social icon"> -->
                                <div class="sociallinks">
                                    <a href="https://www.instagram.com/leadipgmbh/" target="_blank"><span><img src="{{ env('APP_URL') }}/image/Insta.png" alt="instagram"></span></a>
                                    <a href="https://twitter.com/leadipgmbh" target="_blank"><span><img src="{{ env('APP_URL') }}/image/Twitter.png" alt="twitter"></span></a>
                                    <a href="https://www.linkedin.com/company/lead-ip/" target="_blank"><span><img src="{{ env('APP_URL') }}/image/Linkedin.png" alt="linkedin"></span></a>
                                    <a href="https://www.facebook.com/leadipgmbh/" target="_blank"><img src="{{ env('APP_URL') }}/image/facebook.png" alt="facebook"><span></span></a>
                                    <a href="https://www.xing.com/pages/lead-ip-gmbh" target="_blank"><img src="{{ env('APP_URL') }}/image/1.png" alt="xing"><span></span></a>
                                </div>
                                <p style="color: #ffffff;  margin-bottom: 21px; text-align: center; ">Our Blog |
                                    Unsubscribe
                                    | Policies</p>
                            </div>
                        </footer>
                    </div>
                </div>
            </div>

        </div>
</body>

</html>
