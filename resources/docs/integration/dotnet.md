# .NET

Use Microsoft's own OpenID Connect middleware; no special library is needed.

- [ASP.NET Core (.NET 8 or later)](#aspnet-core-net-8-or-later)
- [ASP.NET MVC 5 / Web Forms (.NET Framework 4.7.2+)](#aspnet-mvc-5--web-forms-net-framework-472)

## ASP.NET Core (.NET 8 or later)

```
dotnet add package Microsoft.AspNetCore.Authentication.OpenIdConnect
```

`appsettings.json` (put the secret in user secrets or an environment variable, not in the file):

```json
"Sso": {
  "Authority": "{{issuer}}",
  "ClientId": "{{client_id}}"
}
```

`Program.cs`:

```csharp
using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.AspNetCore.Authentication.OpenIdConnect;
using Microsoft.IdentityModel.Protocols.OpenIdConnect;

builder.Services.AddAuthentication(options =>
{
    options.DefaultScheme = CookieAuthenticationDefaults.AuthenticationScheme;
    options.DefaultChallengeScheme = OpenIdConnectDefaults.AuthenticationScheme;
})
.AddCookie(options =>
{
    options.ExpireTimeSpan = TimeSpan.FromHours(10);
    options.SlidingExpiration = false;
})
.AddOpenIdConnect(options =>
{
    options.Authority = builder.Configuration["Sso:Authority"];
    options.ClientId = builder.Configuration["Sso:ClientId"];
    options.ClientSecret = builder.Configuration["Sso:ClientSecret"];
    options.ResponseType = OpenIdConnectResponseType.Code;
    options.UsePkce = true;                       // required by the SSO

    options.Scope.Clear();
    foreach (var scope in "{{scopes}}".Split(' ')) options.Scope.Add(scope);

    options.SaveTokens = true;                    // keeps id_token for sign-out
    options.MapInboundClaims = false;             // keep the SSO's claim names
    options.TokenValidationParameters.NameClaimType = "name";
    options.TokenValidationParameters.RoleClaimType = "roles";

    // Development SSO over http only. Remove for https://sso.example.com.
    options.RequireHttpsMetadata = false;
});

builder.Services.AddAuthorization(o => o.FallbackPolicy = o.DefaultPolicy); // sign-in required everywhere

// ...
app.UseAuthentication();
app.UseAuthorization();
```

Use it:

```csharp
User.Identity!.Name;                    // Ahmed Khan
User.FindFirst("user_id")?.Value;       // old SSO UserID
User.FindFirst("preferred_username")?.Value;
User.FindFirst("emp_id")?.Value;
User.IsInRole("clerk");                 // this application's roles

[Authorize(Roles = "approver")]
public IActionResult Approve() { ... }
```

Sign out of the application and the SSO:

```csharp
[HttpPost("/logout")]
public IActionResult Logout() => SignOut(
    new AuthenticationProperties { RedirectUri = "/" },
    CookieAuthenticationDefaults.AuthenticationScheme,
    OpenIdConnectDefaults.AuthenticationScheme);
```

Register at the SSO:

| | |
|---|---|
| Redirect URI | `{{dotnet_redirect_uri}}` |
| Post-logout redirect URI | `{{app_url}}signout-callback-oidc` |
| Back-channel logout URI (optional, below) | `{{dotnet_backchannel_uri}}` |

### Back-channel logout (ASP.NET Core)

ASP.NET Core has no built-in back-channel logout. This endpoint verifies the SSO's logout token and remembers the ended session; the cookie check signs the user out on their next request.

```csharp
using Microsoft.Extensions.Caching.Memory;
using Microsoft.Extensions.Options;
using Microsoft.IdentityModel.JsonWebTokens;
using Microsoft.IdentityModel.Tokens;

builder.Services.AddMemoryCache();   // use a distributed cache if you run several servers

// Store the SSO session id (sid) in the cookie at sign-in, and reject cookies whose session ended.
builder.Services.Configure<CookieAuthenticationOptions>(CookieAuthenticationDefaults.AuthenticationScheme, o =>
    o.Events.OnValidatePrincipal = ctx =>
    {
        var sid = ctx.Principal?.FindFirst("sid")?.Value;
        var ended = ctx.HttpContext.RequestServices.GetRequiredService<IMemoryCache>();
        if (sid != null && ended.TryGetValue("sso-ended:" + sid, out _)) ctx.RejectPrincipal();
        return Task.CompletedTask;
    });

app.MapPost("/signout-backchannel", async (HttpContext http, IMemoryCache ended,
    IOptionsMonitor<OpenIdConnectOptions> oidc) =>
{
    var form = await http.Request.ReadFormAsync();
    var options = oidc.Get(OpenIdConnectDefaults.AuthenticationScheme);
    var config = await options.ConfigurationManager!.GetConfigurationAsync(http.RequestAborted);

    var result = await new JsonWebTokenHandler().ValidateTokenAsync(form["logout_token"].ToString(),
        new TokenValidationParameters
        {
            ValidIssuer = config.Issuer,
            ValidAudience = options.ClientId,
            IssuerSigningKeys = config.SigningKeys,
        });

    if (!result.IsValid
        || !result.Claims.TryGetValue("sid", out var sid)
        || result.Claims.ContainsKey("nonce")
        || !(result.Claims.TryGetValue("events", out var events)
             && events.ToString()!.Contains("http://schemas.openid.net/event/backchannel-logout")))
        return Results.BadRequest();

    ended.Set("sso-ended:" + sid, true, TimeSpan.FromHours(12));
    return Results.Ok();
}).AllowAnonymous().DisableAntiforgery();
```

## ASP.NET MVC 5 / Web Forms (.NET Framework 4.7.2+)

Install with NuGet (**version 4.2 or later**, which supports PKCE):

```
Install-Package Microsoft.Owin.Host.SystemWeb
Install-Package Microsoft.Owin.Security.Cookies
Install-Package Microsoft.Owin.Security.OpenIdConnect
```

`Startup.cs` (add `[assembly: OwinStartup(typeof(YourApp.Startup))]`):

```csharp
using System.IdentityModel.Tokens.Jwt;
using Microsoft.IdentityModel.Protocols.OpenIdConnect;
using Microsoft.IdentityModel.Tokens;
using Microsoft.Owin.Host.SystemWeb;
using Microsoft.Owin.Security;
using Microsoft.Owin.Security.Cookies;
using Microsoft.Owin.Security.OpenIdConnect;
using Owin;

public class Startup
{
    public void Configuration(IAppBuilder app)
    {
        JwtSecurityTokenHandler.DefaultInboundClaimTypeMap.Clear();   // keep the SSO's claim names

        app.SetDefaultSignInAsAuthenticationType(CookieAuthenticationDefaults.AuthenticationType);
        app.UseCookieAuthentication(new CookieAuthenticationOptions
        {
            CookieManager = new SystemWebCookieManager(),   // avoids lost cookies under System.Web
        });

        app.UseOpenIdConnectAuthentication(new OpenIdConnectAuthenticationOptions
        {
            Authority = "{{issuer}}",
            ClientId = "{{client_id}}",
            ClientSecret = ConfigurationManager.AppSettings["SsoClientSecret"],
            RedirectUri = "{{dotnet_redirect_uri}}",
            PostLogoutRedirectUri = "{{post_logout_uri}}",
            ResponseType = OpenIdConnectResponseType.Code,
            RedeemCode = true,
            UsePkce = true,
            Scope = "{{scopes}}",
            SaveTokens = true,
            RequireHttpsMetadata = false,   // development SSO over http only
            TokenValidationParameters = new TokenValidationParameters
            {
                NameClaimType = "name",
                RoleClaimType = "roles",
            },
            CookieManager = new SystemWebCookieManager(),
        });
    }
}
```

Require sign-in everywhere with `GlobalFilters.Filters.Add(new AuthorizeAttribute());` (MVC) or `<authorization><deny users="?" /></authorization>` in `web.config` (Web Forms). Roles work with `[Authorize(Roles = "clerk")]` and `User.IsInRole("clerk")`; other claims via `((ClaimsPrincipal)User).FindFirst("user_id")`.

Sign out:

```csharp
HttpContext.GetOwinContext().Authentication.SignOut(
    OpenIdConnectAuthenticationDefaults.AuthenticationType,
    CookieAuthenticationDefaults.AuthenticationType);
```

Register `{{dotnet_redirect_uri}}` as redirect URI and `{{post_logout_uri}}` as post-logout redirect URI.

## Calling the API from .NET

```csharp
var http = new HttpClient();
var token = await http.PostAsync("{{issuer}}/oauth/token", new FormUrlEncodedContent(new Dictionary<string, string>
{
    ["grant_type"] = "client_credentials",
    ["client_id"] = "{{client_id}}",
    ["client_secret"] = secret,
    ["scope"] = "users.read",
}));
var accessToken = (await token.Content.ReadFromJsonAsync<JsonElement>()).GetProperty("access_token").GetString();

http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", accessToken);
var users = await http.GetFromJsonAsync<JsonElement>("{{issuer}}/api/v1/users?search=ahmed");
```

More in the [API guide](api).
