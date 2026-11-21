# Java (Spring Boot)

Spring Security's OAuth2 client does everything; connecting is mostly configuration. Needs **Spring Boot 3.2+** (Spring Security 6.2+, for back-channel logout).

```xml
<dependency>
  <groupId>org.springframework.boot</groupId>
  <artifactId>spring-boot-starter-oauth2-client</artifactId>
</dependency>
```

## Configuration

`application.yml` (secret from an environment variable):

```yaml
spring:
  security:
    oauth2:
      client:
        registration:
          sso:
            provider: sso
            client-id: {{client_id}}
            client-secret: ${SSO_CLIENT_SECRET}
            authorization-grant-type: authorization_code
            redirect-uri: "{baseUrl}/login/oauth2/code/{registrationId}"
            scope: openid,profile,email,employee,roles
        provider:
          sso:
            issuer-uri: {{issuer}}
            user-name-attribute: preferred_username
```

## Security configuration

The SSO requires **PKCE**. Spring adds it automatically only for public clients, so switch it on explicitly:

```java
import java.util.*;
import java.util.stream.*;
import org.springframework.context.annotation.*;
import org.springframework.security.config.Customizer;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.authority.mapping.GrantedAuthoritiesMapper;
import org.springframework.security.oauth2.client.oidc.web.logout.OidcClientInitiatedLogoutSuccessHandler;
import org.springframework.security.oauth2.client.registration.ClientRegistrationRepository;
import org.springframework.security.oauth2.client.web.*;
import org.springframework.security.oauth2.core.oidc.user.OidcUserAuthority;
import org.springframework.security.web.SecurityFilterChain;

@Configuration
public class SecurityConfig {

    @Bean
    SecurityFilterChain security(HttpSecurity http, ClientRegistrationRepository registrations) throws Exception {
        var authorizationRequests = new DefaultOAuth2AuthorizationRequestResolver(registrations, "/oauth2/authorization");
        authorizationRequests.setAuthorizationRequestCustomizer(OAuth2AuthorizationRequestCustomizers.withPkce());

        var ssoLogout = new OidcClientInitiatedLogoutSuccessHandler(registrations);
        ssoLogout.setPostLogoutRedirectUri("{baseUrl}/");

        http
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/admin/**").hasRole("admin")
                .anyRequest().authenticated())
            .oauth2Login(login -> login
                .authorizationEndpoint(a -> a.authorizationRequestResolver(authorizationRequests))
                .userInfoEndpoint(u -> u.userAuthoritiesMapper(ssoRoles())))
            .logout(logout -> logout.logoutSuccessHandler(ssoLogout))
            .oidcLogout(logout -> logout.backChannel(Customizer.withDefaults()));

        return http.build();
    }

    /** The SSO's "roles" claim (this application's role keys) becomes ROLE_<key>. */
    @Bean
    GrantedAuthoritiesMapper ssoRoles() {
        return authorities -> authorities.stream()
            .filter(OidcUserAuthority.class::isInstance)
            .map(OidcUserAuthority.class::cast)
            .flatMap(a -> Optional.ofNullable(a.getIdToken().getClaimAsStringList("roles")).orElse(List.of()).stream())
            .map(role -> new SimpleGrantedAuthority("ROLE_" + role))
            .collect(Collectors.toSet());
    }
}
```

## Using the signed-in user

```java
@GetMapping("/")
String home(@AuthenticationPrincipal OidcUser user, Model model) {
    model.addAttribute("name", user.getFullName());                    // Ahmed Khan
    model.addAttribute("userId", user.getClaimAsString("user_id"));    // old SSO UserID
    model.addAttribute("empId", user.getClaimAsString("emp_id"));
    model.addAttribute("roles", user.getClaimAsStringList("roles"));
    return "home";
}
```

Roles in code: `@PreAuthorize("hasRole('approver')")` (with `@EnableMethodSecurity`). Sign out with a `POST /logout` form (Spring's default); it signs the user out of the SSO too.

## Register at the SSO

| | |
|---|---|
| Redirect URI | `{{java_redirect_uri}}` |
| Post-logout redirect URI | `{{app_url}}` |
| Back-channel logout URI | `{{java_backchannel_uri}}` |

## Not on Spring?

Jakarta EE / plain servlet applications can use [pac4j](https://www.pac4j.org/) (`pac4j-oidc`) with the same values: discovery URI `{{discovery}}`, client ID and secret, scope `{{scopes}}`, and PKCE S256 enabled.

## Calling the API from Java

```java
var rest = RestClient.create("{{issuer}}");

var form = new LinkedMultiValueMap<String, String>();
form.add("grant_type", "client_credentials");
form.add("client_id", "{{client_id}}");
form.add("client_secret", System.getenv("SSO_CLIENT_SECRET"));
form.add("scope", "users.read");

Map<?, ?> token = rest.post().uri("/oauth/token")
    .contentType(MediaType.APPLICATION_FORM_URLENCODED).body(form)
    .retrieve().body(Map.class);

Map<?, ?> users = rest.get().uri("/api/v1/users?search={q}", "ahmed")
    .header("Authorization", "Bearer " + token.get("access_token"))
    .retrieve().body(Map.class);
```

More in the [API guide](api).
