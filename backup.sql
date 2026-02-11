--
-- PostgreSQL database dump
--

\restrict tq3nTqFzhOLk0oZ2GMH13WpHsRwH7zSD1Tgbsd2VFKBCS20hErVbsPRU6ojv0hK

-- Dumped from database version 18.1
-- Dumped by pg_dump version 18.1

-- Started on 2026-02-06 17:31:42

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 2 (class 3079 OID 17541)
-- Name: unaccent; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA public;


--
-- TOC entry 5264 (class 0 OID 0)
-- Dependencies: 2
-- Name: EXTENSION unaccent; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION unaccent IS 'text search dictionary that removes accents';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 235 (class 1259 OID 17227)
-- Name: USER; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public."USER" (
    id_user integer NOT NULL,
    nombres character varying(64) NOT NULL,
    apellidos character varying(64) NOT NULL,
    cedula character varying(10) NOT NULL,
    password character varying(255) NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    id_agencias integer,
    id_cargo integer,
    id_supervisor integer,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone
);


ALTER TABLE public."USER" OWNER TO postgres;

--
-- TOC entry 234 (class 1259 OID 17226)
-- Name: USER_id_user_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public."USER_id_user_seq"
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public."USER_id_user_seq" OWNER TO postgres;

--
-- TOC entry 5265 (class 0 OID 0)
-- Dependencies: 234
-- Name: USER_id_user_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public."USER_id_user_seq" OWNED BY public."USER".id_user;


--
-- TOC entry 227 (class 1259 OID 17179)
-- Name: agencias; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.agencias (
    id_agencias integer NOT NULL,
    nombre_agencia text NOT NULL,
    direccion character varying(100),
    ciudad character varying(20)
);


ALTER TABLE public.agencias OWNER TO postgres;

--
-- TOC entry 226 (class 1259 OID 17178)
-- Name: agencias_id_agencias_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.agencias_id_agencias_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agencias_id_agencias_seq OWNER TO postgres;

--
-- TOC entry 5266 (class 0 OID 0)
-- Dependencies: 226
-- Name: agencias_id_agencias_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.agencias_id_agencias_seq OWNED BY public.agencias.id_agencias;


--
-- TOC entry 231 (class 1259 OID 17201)
-- Name: area; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.area (
    id_area integer NOT NULL,
    nombre_area character varying(60) NOT NULL,
    id_division integer NOT NULL,
    id_jf_area integer
);


ALTER TABLE public.area OWNER TO postgres;

--
-- TOC entry 230 (class 1259 OID 17200)
-- Name: area_id_area_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.area_id_area_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.area_id_area_seq OWNER TO postgres;

--
-- TOC entry 5267 (class 0 OID 0)
-- Dependencies: 230
-- Name: area_id_area_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.area_id_area_seq OWNED BY public.area.id_area;


--
-- TOC entry 233 (class 1259 OID 17213)
-- Name: cargo; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cargo (
    id_cargo integer NOT NULL,
    nombre_cargo character varying(80) NOT NULL,
    id_area integer,
    id_division integer,
    CONSTRAINT chk_cargo_scope CHECK ((((id_area IS NOT NULL) AND (id_division IS NULL)) OR ((id_area IS NULL) AND (id_division IS NOT NULL))))
);


ALTER TABLE public.cargo OWNER TO postgres;

--
-- TOC entry 232 (class 1259 OID 17212)
-- Name: cargo_id_cargo_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cargo_id_cargo_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cargo_id_cargo_seq OWNER TO postgres;

--
-- TOC entry 5268 (class 0 OID 0)
-- Dependencies: 232
-- Name: cargo_id_cargo_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cargo_id_cargo_seq OWNED BY public.cargo.id_cargo;


--
-- TOC entry 225 (class 1259 OID 17165)
-- Name: catalogo_metrica; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.catalogo_metrica (
    id_metrica smallint NOT NULL,
    clave_metrica character varying(50) NOT NULL,
    nombre_metrica character varying(120) NOT NULL,
    unidad character varying(20) DEFAULT 'COUNT'::character varying NOT NULL
);


ALTER TABLE public.catalogo_metrica OWNER TO postgres;

--
-- TOC entry 224 (class 1259 OID 17164)
-- Name: catalogo_metrica_id_metrica_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.catalogo_metrica_id_metrica_seq
    AS smallint
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.catalogo_metrica_id_metrica_seq OWNER TO postgres;

--
-- TOC entry 5269 (class 0 OID 0)
-- Dependencies: 224
-- Name: catalogo_metrica_id_metrica_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.catalogo_metrica_id_metrica_seq OWNED BY public.catalogo_metrica.id_metrica;


--
-- TOC entry 229 (class 1259 OID 17190)
-- Name: division; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.division (
    id_division integer NOT NULL,
    nombre_division character varying(50) NOT NULL,
    id_jf_division integer
);


ALTER TABLE public.division OWNER TO postgres;

--
-- TOC entry 228 (class 1259 OID 17189)
-- Name: division_id_division_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.division_id_division_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.division_id_division_seq OWNER TO postgres;

--
-- TOC entry 5270 (class 0 OID 0)
-- Dependencies: 228
-- Name: division_id_division_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.division_id_division_seq OWNED BY public.division.id_division;


--
-- TOC entry 246 (class 1259 OID 17331)
-- Name: division_metrica; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.division_metrica (
    id_division integer NOT NULL,
    id_metrica smallint NOT NULL,
    active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.division_metrica OWNER TO postgres;

--
-- TOC entry 223 (class 1259 OID 17154)
-- Name: estado_tarea; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.estado_tarea (
    id_estado_tarea smallint NOT NULL,
    nombre_estado character varying(30) NOT NULL
);


ALTER TABLE public.estado_tarea OWNER TO postgres;

--
-- TOC entry 222 (class 1259 OID 17153)
-- Name: estado_tarea_id_estado_tarea_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.estado_tarea_id_estado_tarea_seq
    AS smallint
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.estado_tarea_id_estado_tarea_seq OWNER TO postgres;

--
-- TOC entry 5271 (class 0 OID 0)
-- Dependencies: 222
-- Name: estado_tarea_id_estado_tarea_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.estado_tarea_id_estado_tarea_seq OWNED BY public.estado_tarea.id_estado_tarea;


--
-- TOC entry 237 (class 1259 OID 17245)
-- Name: historico; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.historico (
    id_historico bigint NOT NULL,
    semana date NOT NULL,
    estado character varying(50),
    id_user integer NOT NULL,
    id_agencias integer,
    id_division integer,
    id_area integer,
    id_cargo integer,
    id_supervisor integer,
    nombres character varying(120) NOT NULL,
    apellidos character varying(120) NOT NULL,
    cedula character varying(20) NOT NULL,
    area_nombre character varying(160),
    cargo_nombre character varying(160),
    jefe_inmediato character varying(160) NOT NULL,
    condicion character varying(20) NOT NULL,
    preguntas_json text,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.historico OWNER TO postgres;

--
-- TOC entry 236 (class 1259 OID 17244)
-- Name: historico_id_historico_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.historico_id_historico_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.historico_id_historico_seq OWNER TO postgres;

--
-- TOC entry 5272 (class 0 OID 0)
-- Dependencies: 236
-- Name: historico_id_historico_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.historico_id_historico_seq OWNED BY public.historico.id_historico;


--
-- TOC entry 248 (class 1259 OID 17343)
-- Name: link_sna_schedule; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.link_sna_schedule (
    id integer NOT NULL,
    enable_dow smallint NOT NULL,
    enable_time time without time zone NOT NULL,
    disable_dow smallint NOT NULL,
    disable_time time without time zone NOT NULL,
    timezone character varying(64) DEFAULT 'America/Guayaquil'::character varying NOT NULL,
    active boolean DEFAULT true NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.link_sna_schedule OWNER TO postgres;

--
-- TOC entry 247 (class 1259 OID 17342)
-- Name: link_sna_schedule_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.link_sna_schedule ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.link_sna_schedule_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 243 (class 1259 OID 17298)
-- Name: objetivo_division_semana; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.objetivo_division_semana (
    id_objetivo bigint NOT NULL,
    id_division integer NOT NULL,
    week_start date NOT NULL,
    id_metrica smallint NOT NULL,
    valor_objetivo numeric(14,2) DEFAULT 0 NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.objetivo_division_semana OWNER TO postgres;

--
-- TOC entry 242 (class 1259 OID 17297)
-- Name: objetivo_division_semana_id_objetivo_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.objetivo_division_semana_id_objetivo_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.objetivo_division_semana_id_objetivo_seq OWNER TO postgres;

--
-- TOC entry 5273 (class 0 OID 0)
-- Dependencies: 242
-- Name: objetivo_division_semana_id_objetivo_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.objetivo_division_semana_id_objetivo_seq OWNED BY public.objetivo_division_semana.id_objetivo;


--
-- TOC entry 245 (class 1259 OID 17315)
-- Name: objetivo_usuario_semana; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.objetivo_usuario_semana (
    id_objetivo bigint NOT NULL,
    id_user integer NOT NULL,
    week_start date NOT NULL,
    id_metrica smallint NOT NULL,
    valor_objetivo numeric(14,2) DEFAULT 0 NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.objetivo_usuario_semana OWNER TO postgres;

--
-- TOC entry 244 (class 1259 OID 17314)
-- Name: objetivo_usuario_semana_id_objetivo_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.objetivo_usuario_semana_id_objetivo_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.objetivo_usuario_semana_id_objetivo_seq OWNER TO postgres;

--
-- TOC entry 5274 (class 0 OID 0)
-- Dependencies: 244
-- Name: objetivo_usuario_semana_id_objetivo_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.objetivo_usuario_semana_id_objetivo_seq OWNED BY public.objetivo_usuario_semana.id_objetivo;


--
-- TOC entry 221 (class 1259 OID 17143)
-- Name: prioridad; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.prioridad (
    id_prioridad smallint NOT NULL,
    nombre_prioridad character varying(30) NOT NULL
);


ALTER TABLE public.prioridad OWNER TO postgres;

--
-- TOC entry 220 (class 1259 OID 17142)
-- Name: prioridad_id_prioridad_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.prioridad_id_prioridad_seq
    AS smallint
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.prioridad_id_prioridad_seq OWNER TO postgres;

--
-- TOC entry 5275 (class 0 OID 0)
-- Dependencies: 220
-- Name: prioridad_id_prioridad_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.prioridad_id_prioridad_seq OWNED BY public.prioridad.id_prioridad;


--
-- TOC entry 239 (class 1259 OID 17266)
-- Name: tareas; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tareas (
    id_tarea bigint NOT NULL,
    titulo character varying(180) NOT NULL,
    descripcion text,
    id_prioridad smallint NOT NULL,
    id_estado_tarea smallint NOT NULL,
    fecha_inicio timestamp with time zone NOT NULL,
    fecha_fin timestamp with time zone,
    id_area integer NOT NULL,
    asignado_a integer NOT NULL,
    asignado_por integer NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    completed_at timestamp with time zone
);


ALTER TABLE public.tareas OWNER TO postgres;

--
-- TOC entry 238 (class 1259 OID 17265)
-- Name: tareas_id_tarea_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tareas_id_tarea_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tareas_id_tarea_seq OWNER TO postgres;

--
-- TOC entry 5276 (class 0 OID 0)
-- Dependencies: 238
-- Name: tareas_id_tarea_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tareas_id_tarea_seq OWNED BY public.tareas.id_tarea;


--
-- TOC entry 251 (class 1259 OID 17520)
-- Name: usuario_cargo; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.usuario_cargo (
    id_user integer NOT NULL,
    id_cargo integer NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.usuario_cargo OWNER TO postgres;

--
-- TOC entry 241 (class 1259 OID 17285)
-- Name: ventas; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ventas (
    id_venta bigint NOT NULL,
    id_user integer NOT NULL,
    fecha_venta timestamp with time zone DEFAULT now() NOT NULL,
    monto numeric(12,2) DEFAULT 0 NOT NULL,
    canal character varying(30),
    referencia character varying(60)
);


ALTER TABLE public.ventas OWNER TO postgres;

--
-- TOC entry 240 (class 1259 OID 17284)
-- Name: ventas_id_venta_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ventas_id_venta_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ventas_id_venta_seq OWNER TO postgres;

--
-- TOC entry 5277 (class 0 OID 0)
-- Dependencies: 240
-- Name: ventas_id_venta_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ventas_id_venta_seq OWNED BY public.ventas.id_venta;


--
-- TOC entry 250 (class 1259 OID 17508)
-- Name: vw_metricas_semanales_usuario; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.vw_metricas_semanales_usuario AS
 WITH tareas_asignadas AS (
         SELECT (date_trunc('week'::text, t.fecha_inicio))::date AS week_start,
            t.asignado_a AS id_user,
            'TAREAS_ASIGNADAS'::character varying AS clave_metrica,
            (count(*))::numeric AS valor_real
           FROM public.tareas t
          GROUP BY ((date_trunc('week'::text, t.fecha_inicio))::date), t.asignado_a
        ), tareas_creadas AS (
         SELECT (date_trunc('week'::text, t.fecha_inicio))::date AS week_start,
            t.asignado_por AS id_user,
            'TAREAS_CREADAS'::character varying AS clave_metrica,
            (count(*))::numeric AS valor_real
           FROM public.tareas t
          GROUP BY ((date_trunc('week'::text, t.fecha_inicio))::date), t.asignado_por
        ), tareas_cerradas AS (
         SELECT (date_trunc('week'::text, t.completed_at))::date AS week_start,
            t.asignado_a AS id_user,
            'TAREAS_CERRADAS'::character varying AS clave_metrica,
            (count(*))::numeric AS valor_real
           FROM public.tareas t
          WHERE (t.completed_at IS NOT NULL)
          GROUP BY ((date_trunc('week'::text, t.completed_at))::date), t.asignado_a
        ), ventas_count AS (
         SELECT (date_trunc('week'::text, v.fecha_venta))::date AS week_start,
            v.id_user,
            'VENTAS_COUNT'::character varying AS clave_metrica,
            (count(*))::numeric AS valor_real
           FROM public.ventas v
          GROUP BY ((date_trunc('week'::text, v.fecha_venta))::date), v.id_user
        ), ventas_monto AS (
         SELECT (date_trunc('week'::text, v.fecha_venta))::date AS week_start,
            v.id_user,
            'VENTAS_MONTO'::character varying AS clave_metrica,
            COALESCE(sum(v.monto), (0)::numeric) AS valor_real
           FROM public.ventas v
          GROUP BY ((date_trunc('week'::text, v.fecha_venta))::date), v.id_user
        )
 SELECT tareas_asignadas.week_start,
    tareas_asignadas.id_user,
    tareas_asignadas.clave_metrica,
    tareas_asignadas.valor_real
   FROM tareas_asignadas
UNION ALL
 SELECT tareas_creadas.week_start,
    tareas_creadas.id_user,
    tareas_creadas.clave_metrica,
    tareas_creadas.valor_real
   FROM tareas_creadas
UNION ALL
 SELECT tareas_cerradas.week_start,
    tareas_cerradas.id_user,
    tareas_cerradas.clave_metrica,
    tareas_cerradas.valor_real
   FROM tareas_cerradas
UNION ALL
 SELECT ventas_count.week_start,
    ventas_count.id_user,
    ventas_count.clave_metrica,
    ventas_count.valor_real
   FROM ventas_count
UNION ALL
 SELECT ventas_monto.week_start,
    ventas_monto.id_user,
    ventas_monto.clave_metrica,
    ventas_monto.valor_real
   FROM ventas_monto;


ALTER VIEW public.vw_metricas_semanales_usuario OWNER TO postgres;

--
-- TOC entry 249 (class 1259 OID 17504)
-- Name: vw_usuario_division; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.vw_usuario_division AS
 SELECT u.id_user,
    COALESCE(a.id_division, c.id_division) AS id_division
   FROM ((public."USER" u
     LEFT JOIN public.cargo c ON ((c.id_cargo = u.id_cargo)))
     LEFT JOIN public.area a ON ((a.id_area = c.id_area)));


ALTER VIEW public.vw_usuario_division OWNER TO postgres;

--
-- TOC entry 4952 (class 2604 OID 17230)
-- Name: USER id_user; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."USER" ALTER COLUMN id_user SET DEFAULT nextval('public."USER_id_user_seq"'::regclass);


--
-- TOC entry 4948 (class 2604 OID 17182)
-- Name: agencias id_agencias; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.agencias ALTER COLUMN id_agencias SET DEFAULT nextval('public.agencias_id_agencias_seq'::regclass);


--
-- TOC entry 4950 (class 2604 OID 17204)
-- Name: area id_area; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.area ALTER COLUMN id_area SET DEFAULT nextval('public.area_id_area_seq'::regclass);


--
-- TOC entry 4951 (class 2604 OID 17216)
-- Name: cargo id_cargo; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cargo ALTER COLUMN id_cargo SET DEFAULT nextval('public.cargo_id_cargo_seq'::regclass);


--
-- TOC entry 4946 (class 2604 OID 17168)
-- Name: catalogo_metrica id_metrica; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.catalogo_metrica ALTER COLUMN id_metrica SET DEFAULT nextval('public.catalogo_metrica_id_metrica_seq'::regclass);


--
-- TOC entry 4949 (class 2604 OID 17193)
-- Name: division id_division; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.division ALTER COLUMN id_division SET DEFAULT nextval('public.division_id_division_seq'::regclass);


--
-- TOC entry 4945 (class 2604 OID 17157)
-- Name: estado_tarea id_estado_tarea; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.estado_tarea ALTER COLUMN id_estado_tarea SET DEFAULT nextval('public.estado_tarea_id_estado_tarea_seq'::regclass);


--
-- TOC entry 4955 (class 2604 OID 17248)
-- Name: historico id_historico; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historico ALTER COLUMN id_historico SET DEFAULT nextval('public.historico_id_historico_seq'::regclass);


--
-- TOC entry 4962 (class 2604 OID 17301)
-- Name: objetivo_division_semana id_objetivo; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.objetivo_division_semana ALTER COLUMN id_objetivo SET DEFAULT nextval('public.objetivo_division_semana_id_objetivo_seq'::regclass);


--
-- TOC entry 4965 (class 2604 OID 17318)
-- Name: objetivo_usuario_semana id_objetivo; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.objetivo_usuario_semana ALTER COLUMN id_objetivo SET DEFAULT nextval('public.objetivo_usuario_semana_id_objetivo_seq'::regclass);


--
-- TOC entry 4944 (class 2604 OID 17146)
-- Name: prioridad id_prioridad; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.prioridad ALTER COLUMN id_prioridad SET DEFAULT nextval('public.prioridad_id_prioridad_seq'::regclass);


--
-- TOC entry 4957 (class 2604 OID 17269)
-- Name: tareas id_tarea; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tareas ALTER COLUMN id_tarea SET DEFAULT nextval('public.tareas_id_tarea_seq'::regclass);


--
-- TOC entry 4959 (class 2604 OID 17288)
-- Name: ventas id_venta; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ventas ALTER COLUMN id_venta SET DEFAULT nextval('public.ventas_id_venta_seq'::regclass);


--
-- TOC entry 5244 (class 0 OID 17227)
-- Dependencies: 235
-- Data for Name: USER; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public."USER" (id_user, nombres, apellidos, cedula, password, activo, id_agencias, id_cargo, id_supervisor, created_at, updated_at) FROM stdin;
1	Administrador	Mantenimiento	1234567890	$2y$10$/U4rsnJPzxhtrCNog5OuVOVGFzLPbfkbmYM/T2niJEvCy/TOhxaK2	t	\N	1	\N	2026-02-05 10:31:00.906326-05	\N
2	Gabriela	Serrano	1234567899	$2y$10$YVlokjnyx37UpfHuPJrbrOyamK8cp8qTC4giNiQsBHc2uCNE78SZ2	t	1	6	\N	2026-02-05 16:36:10.755937-05	\N
4	Oscar	Ulloa	1234567810	$2y$10$QUgagUrrQb1yEEH.3.jPfeXGnBMZHB8FxcNII4CHR1/4TruE98zDy	t	1	2	2	2026-02-06 11:05:08.400675-05	\N
5	Fausto	Urbano	1234567811	$2y$10$OtlXGM8K4D3GaxHoNvZu5eRVxVNAK7wVM1lNQQ76vXdEfudOPph.S	t	1	3	4	2026-02-06 11:11:28.709346-05	\N
6	Gixon 	Andrade	1753073350	$2y$10$ZUOEc/QAkuwOpFi3zB6hJuIkitm6zNHNz0y4NyxbJdrsEI9YHewTq	t	1	5	5	2026-02-06 11:12:28.222696-05	\N
7	Darien 	Herdoiza	1234567892	$2y$10$zb4QHvkLAC59pMiN3F2vVuRnTFaUf2m65sVj97MM2yUMryZoJVnoK	t	1	5	5	2026-02-06 11:21:43.861092-05	\N
8	Andres 	Garcia	1234567893	$2y$10$pvLY7giWMyv8znDeBTWlHOTZicoXyyg0dj.A.OXwCYiiprqwUnxw6	t	1	5	5	2026-02-06 12:32:28.402299-05	\N
9	Cristian 	Proaño	1234567894	$2y$10$kOqmqpFvJjWBFtbCzTnC.e4BA/Cl1plCuP97ijcjA/IsGLVcpfX9a	t	1	5	5	2026-02-06 12:33:29.742681-05	\N
10	Richard	Navarrete	1234567888	$2y$10$9IuKnF2Nx/fp/RJhX6apfutjdPzxPM68pOTw4yAz5mfpmprTe5ege	t	1	7	4	2026-02-06 13:07:31.313053-05	\N
11	Darwin	Torres	1234567654	$2y$10$imBDqLFmUkRReiH.Cw4KUuAyo3O0oucaDy0.jHvT8VvAoc4/5q/oy	t	1	7	4	2026-02-06 13:29:21.314312-05	\N
\.


--
-- TOC entry 5236 (class 0 OID 17179)
-- Dependencies: 227
-- Data for Name: agencias; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.agencias (id_agencias, nombre_agencia, direccion, ciudad) FROM stdin;
1	Matriz	Av.10 de Agosto	Quito
\.


--
-- TOC entry 5240 (class 0 OID 17201)
-- Dependencies: 231
-- Data for Name: area; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.area (id_area, nombre_area, id_division, id_jf_area) FROM stdin;
1	Mantenimiento	1	1
4	Gerencia General	12	\N
3	Analítica	2	4
2	Sistemas	2	5
\.


--
-- TOC entry 5242 (class 0 OID 17213)
-- Dependencies: 233
-- Data for Name: cargo; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cargo (id_cargo, nombre_cargo, id_area, id_division) FROM stdin;
1	Administrador	1	\N
2	Jefe de Division	\N	2
3	Jefe de Área	2	\N
4	Jefe de Área	3	\N
5	Asistente	2	\N
6	Gerente	4	\N
7	Asistente	3	\N
\.


--
-- TOC entry 5234 (class 0 OID 17165)
-- Dependencies: 225
-- Data for Name: catalogo_metrica; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.catalogo_metrica (id_metrica, clave_metrica, nombre_metrica, unidad) FROM stdin;
1	VENTAS_COUNT	Número de ventas	COUNT
2	VENTAS_MONTO	Monto total de ventas	AMOUNT
3	TAREAS_ASIGNADAS	Tareas asignadas al usuario (asignado_a)	COUNT
4	TAREAS_CREADAS	Tareas creadas por el usuario (asignado_por)	COUNT
5	TAREAS_CERRADAS	Tareas finalizadas por el usuario	COUNT
\.


--
-- TOC entry 5238 (class 0 OID 17190)
-- Dependencies: 229
-- Data for Name: division; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.division (id_division, nombre_division, id_jf_division) FROM stdin;
3	Desarrollo Organizacional	\N
4	Comercial Retail	\N
5	Call Center	\N
6	Marketing	\N
7	Riesgo	\N
8	Cobranzas	\N
9	Contabilidad	\N
10	Operaciones	\N
11	Nuevos Negocios	\N
12	Dirección General	\N
2	Tecnologías de la Información	4
1	Administrador	\N
\.


--
-- TOC entry 5255 (class 0 OID 17331)
-- Dependencies: 246
-- Data for Name: division_metrica; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.division_metrica (id_division, id_metrica, active, created_at) FROM stdin;
\.


--
-- TOC entry 5232 (class 0 OID 17154)
-- Dependencies: 223
-- Data for Name: estado_tarea; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.estado_tarea (id_estado_tarea, nombre_estado) FROM stdin;
1	Pendiente
2	En Progreso
3	Bloqueada
4	Finalizada
5	Cancelada
\.


--
-- TOC entry 5246 (class 0 OID 17245)
-- Dependencies: 237
-- Data for Name: historico; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.historico (id_historico, semana, estado, id_user, id_agencias, id_division, id_area, id_cargo, id_supervisor, nombres, apellidos, cedula, area_nombre, cargo_nombre, jefe_inmediato, condicion, preguntas_json, created_at) FROM stdin;
\.


--
-- TOC entry 5257 (class 0 OID 17343)
-- Dependencies: 248
-- Data for Name: link_sna_schedule; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.link_sna_schedule (id, enable_dow, enable_time, disable_dow, disable_time, timezone, active, updated_at) FROM stdin;
1	5	16:00:00	5	01:05:00	America/Guayaquil	t	2026-02-06 17:22:36-05
\.


--
-- TOC entry 5252 (class 0 OID 17298)
-- Dependencies: 243
-- Data for Name: objetivo_division_semana; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.objetivo_division_semana (id_objetivo, id_division, week_start, id_metrica, valor_objetivo, created_at) FROM stdin;
\.


--
-- TOC entry 5254 (class 0 OID 17315)
-- Dependencies: 245
-- Data for Name: objetivo_usuario_semana; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.objetivo_usuario_semana (id_objetivo, id_user, week_start, id_metrica, valor_objetivo, created_at) FROM stdin;
\.


--
-- TOC entry 5230 (class 0 OID 17143)
-- Dependencies: 221
-- Data for Name: prioridad; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.prioridad (id_prioridad, nombre_prioridad) FROM stdin;
1	Baja
2	Media
3	Alta
4	Crítica
\.


--
-- TOC entry 5248 (class 0 OID 17266)
-- Dependencies: 239
-- Data for Name: tareas; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tareas (id_tarea, titulo, descripcion, id_prioridad, id_estado_tarea, fecha_inicio, fecha_fin, id_area, asignado_a, asignado_por, created_at, completed_at) FROM stdin;
\.


--
-- TOC entry 5258 (class 0 OID 17520)
-- Dependencies: 251
-- Data for Name: usuario_cargo; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.usuario_cargo (id_user, id_cargo, created_at) FROM stdin;
2	6	2026-02-05 16:36:10.755937-05
4	2	2026-02-06 11:05:08.400675-05
4	4	2026-02-06 11:05:08.400675-05
5	3	2026-02-06 11:11:28.709346-05
6	5	2026-02-06 11:12:28.222696-05
7	5	2026-02-06 12:29:36.170554-05
8	5	2026-02-06 12:32:28.402299-05
9	5	2026-02-06 12:33:29.742681-05
10	7	2026-02-06 13:07:31.313053-05
11	7	2026-02-06 14:39:52.118612-05
\.


--
-- TOC entry 5250 (class 0 OID 17285)
-- Dependencies: 241
-- Data for Name: ventas; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.ventas (id_venta, id_user, fecha_venta, monto, canal, referencia) FROM stdin;
\.


--
-- TOC entry 5278 (class 0 OID 0)
-- Dependencies: 234
-- Name: USER_id_user_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public."USER_id_user_seq"', 11, true);


--
-- TOC entry 5279 (class 0 OID 0)
-- Dependencies: 226
-- Name: agencias_id_agencias_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.agencias_id_agencias_seq', 1, true);


--
-- TOC entry 5280 (class 0 OID 0)
-- Dependencies: 230
-- Name: area_id_area_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.area_id_area_seq', 4, true);


--
-- TOC entry 5281 (class 0 OID 0)
-- Dependencies: 232
-- Name: cargo_id_cargo_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cargo_id_cargo_seq', 7, true);


--
-- TOC entry 5282 (class 0 OID 0)
-- Dependencies: 224
-- Name: catalogo_metrica_id_metrica_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.catalogo_metrica_id_metrica_seq', 5, true);


--
-- TOC entry 5283 (class 0 OID 0)
-- Dependencies: 228
-- Name: division_id_division_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.division_id_division_seq', 13, true);


--
-- TOC entry 5284 (class 0 OID 0)
-- Dependencies: 222
-- Name: estado_tarea_id_estado_tarea_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.estado_tarea_id_estado_tarea_seq', 5, true);


--
-- TOC entry 5285 (class 0 OID 0)
-- Dependencies: 236
-- Name: historico_id_historico_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.historico_id_historico_seq', 1, false);


--
-- TOC entry 5286 (class 0 OID 0)
-- Dependencies: 247
-- Name: link_sna_schedule_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.link_sna_schedule_id_seq', 1, true);


--
-- TOC entry 5287 (class 0 OID 0)
-- Dependencies: 242
-- Name: objetivo_division_semana_id_objetivo_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.objetivo_division_semana_id_objetivo_seq', 1, false);


--
-- TOC entry 5288 (class 0 OID 0)
-- Dependencies: 244
-- Name: objetivo_usuario_semana_id_objetivo_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.objetivo_usuario_semana_id_objetivo_seq', 1, false);


--
-- TOC entry 5289 (class 0 OID 0)
-- Dependencies: 220
-- Name: prioridad_id_prioridad_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.prioridad_id_prioridad_seq', 4, true);


--
-- TOC entry 5290 (class 0 OID 0)
-- Dependencies: 238
-- Name: tareas_id_tarea_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tareas_id_tarea_seq', 1, false);


--
-- TOC entry 5291 (class 0 OID 0)
-- Dependencies: 240
-- Name: ventas_id_venta_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.ventas_id_venta_seq', 1, false);


--
-- TOC entry 5009 (class 2606 OID 17243)
-- Name: USER USER_cedula_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."USER"
    ADD CONSTRAINT "USER_cedula_key" UNIQUE (cedula);


--
-- TOC entry 5011 (class 2606 OID 17241)
-- Name: USER USER_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."USER"
    ADD CONSTRAINT "USER_pkey" PRIMARY KEY (id_user);


--
-- TOC entry 4988 (class 2606 OID 17188)
-- Name: agencias agencias_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.agencias
    ADD CONSTRAINT agencias_pkey PRIMARY KEY (id_agencias);


--
-- TOC entry 4995 (class 2606 OID 17209)
-- Name: area area_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.area
    ADD CONSTRAINT area_pkey PRIMARY KEY (id_area);


--
-- TOC entry 5001 (class 2606 OID 17221)
-- Name: cargo cargo_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cargo
    ADD CONSTRAINT cargo_pkey PRIMARY KEY (id_cargo);


--
-- TOC entry 4984 (class 2606 OID 17177)
-- Name: catalogo_metrica catalogo_metrica_clave_metrica_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.catalogo_metrica
    ADD CONSTRAINT catalogo_metrica_clave_metrica_key UNIQUE (clave_metrica);


--
-- TOC entry 4986 (class 2606 OID 17175)
-- Name: catalogo_metrica catalogo_metrica_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.catalogo_metrica
    ADD CONSTRAINT catalogo_metrica_pkey PRIMARY KEY (id_metrica);


--
-- TOC entry 5044 (class 2606 OID 17341)
-- Name: division_metrica division_metrica_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.division_metrica
    ADD CONSTRAINT division_metrica_pkey PRIMARY KEY (id_division, id_metrica);


--
-- TOC entry 4990 (class 2606 OID 17199)
-- Name: division division_nombre_division_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.division
    ADD CONSTRAINT division_nombre_division_key UNIQUE (nombre_division);


--
-- TOC entry 4992 (class 2606 OID 17197)
-- Name: division division_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.division
    ADD CONSTRAINT division_pkey PRIMARY KEY (id_division);


--
-- TOC entry 4980 (class 2606 OID 17163)
-- Name: estado_tarea estado_tarea_nombre_estado_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.estado_tarea
    ADD CONSTRAINT estado_tarea_nombre_estado_key UNIQUE (nombre_estado);


--
-- TOC entry 4982 (class 2606 OID 17161)
-- Name: estado_tarea estado_tarea_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.estado_tarea
    ADD CONSTRAINT estado_tarea_pkey PRIMARY KEY (id_estado_tarea);


--
-- TOC entry 5017 (class 2606 OID 17262)
-- Name: historico historico_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historico
    ADD CONSTRAINT historico_pkey PRIMARY KEY (id_historico);


--
-- TOC entry 5047 (class 2606 OID 17358)
-- Name: link_sna_schedule link_sna_schedule_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.link_sna_schedule
    ADD CONSTRAINT link_sna_schedule_pkey PRIMARY KEY (id);


--
-- TOC entry 5035 (class 2606 OID 17311)
-- Name: objetivo_division_semana objetivo_division_semana_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.objetivo_division_semana
    ADD CONSTRAINT objetivo_division_semana_pkey PRIMARY KEY (id_objetivo);


--
-- TOC entry 5040 (class 2606 OID 17328)
-- Name: objetivo_usuario_semana objetivo_usuario_semana_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.objetivo_usuario_semana
    ADD CONSTRAINT objetivo_usuario_semana_pkey PRIMARY KEY (id_objetivo);


--
-- TOC entry 4976 (class 2606 OID 17152)
-- Name: prioridad prioridad_nombre_prioridad_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.prioridad
    ADD CONSTRAINT prioridad_nombre_prioridad_key UNIQUE (nombre_prioridad);


--
-- TOC entry 4978 (class 2606 OID 17150)
-- Name: prioridad prioridad_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.prioridad
    ADD CONSTRAINT prioridad_pkey PRIMARY KEY (id_prioridad);


--
-- TOC entry 5028 (class 2606 OID 17283)
-- Name: tareas tareas_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tareas
    ADD CONSTRAINT tareas_pkey PRIMARY KEY (id_tarea);


--
-- TOC entry 4999 (class 2606 OID 17211)
-- Name: area uq_area_division_nombre; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.area
    ADD CONSTRAINT uq_area_division_nombre UNIQUE (id_division, nombre_area);


--
-- TOC entry 5005 (class 2606 OID 17223)
-- Name: cargo uq_cargo_area_nombre; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cargo
    ADD CONSTRAINT uq_cargo_area_nombre UNIQUE (id_area, nombre_cargo);


--
-- TOC entry 5007 (class 2606 OID 17225)
-- Name: cargo uq_cargo_division_nombre; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cargo
    ADD CONSTRAINT uq_cargo_division_nombre UNIQUE (id_division, nombre_cargo);


--
-- TOC entry 5021 (class 2606 OID 17264)
-- Name: historico uq_historico_semana_user; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historico
    ADD CONSTRAINT uq_historico_semana_user UNIQUE (semana, id_user);


--
-- TOC entry 5037 (class 2606 OID 17313)
-- Name: objetivo_division_semana uq_obj_div_week_metric; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.objetivo_division_semana
    ADD CONSTRAINT uq_obj_div_week_metric UNIQUE (id_division, week_start, id_metrica);


--
-- TOC entry 5042 (class 2606 OID 17330)
-- Name: objetivo_usuario_semana uq_obj_user_week_metric; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.objetivo_usuario_semana
    ADD CONSTRAINT uq_obj_user_week_metric UNIQUE (id_user, week_start, id_metrica);


--
-- TOC entry 5051 (class 2606 OID 17528)
-- Name: usuario_cargo usuario_cargo_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuario_cargo
    ADD CONSTRAINT usuario_cargo_pkey PRIMARY KEY (id_user, id_cargo);


--
-- TOC entry 5032 (class 2606 OID 17296)
-- Name: ventas ventas_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_pkey PRIMARY KEY (id_venta);


--
-- TOC entry 4996 (class 1259 OID 17488)
-- Name: idx_area_division; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_area_division ON public.area USING btree (id_division);


--
-- TOC entry 4997 (class 1259 OID 17489)
-- Name: idx_area_jf_area; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_area_jf_area ON public.area USING btree (id_jf_area) WHERE (id_jf_area IS NOT NULL);


--
-- TOC entry 5002 (class 1259 OID 17490)
-- Name: idx_cargo_area; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cargo_area ON public.cargo USING btree (id_area) WHERE (id_area IS NOT NULL);


--
-- TOC entry 5003 (class 1259 OID 17491)
-- Name: idx_cargo_division; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cargo_division ON public.cargo USING btree (id_division) WHERE (id_division IS NOT NULL);


--
-- TOC entry 5045 (class 1259 OID 17503)
-- Name: idx_div_metrica_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_div_metrica_active ON public.division_metrica USING btree (id_division, active);


--
-- TOC entry 4993 (class 1259 OID 17519)
-- Name: idx_division_jf_division; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_division_jf_division ON public.division USING btree (id_jf_division) WHERE (id_jf_division IS NOT NULL);


--
-- TOC entry 5018 (class 1259 OID 17492)
-- Name: idx_hist_semana; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_hist_semana ON public.historico USING btree (semana);


--
-- TOC entry 5019 (class 1259 OID 17493)
-- Name: idx_hist_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_hist_user ON public.historico USING btree (id_user);


--
-- TOC entry 5033 (class 1259 OID 17501)
-- Name: idx_obj_div_week; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_obj_div_week ON public.objetivo_division_semana USING btree (id_division, week_start);


--
-- TOC entry 5038 (class 1259 OID 17502)
-- Name: idx_obj_user_week; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_obj_user_week ON public.objetivo_usuario_semana USING btree (id_user, week_start);


--
-- TOC entry 5022 (class 1259 OID 17494)
-- Name: idx_tareas_asignado_a_fecha; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tareas_asignado_a_fecha ON public.tareas USING btree (asignado_a, fecha_inicio);


--
-- TOC entry 5023 (class 1259 OID 17495)
-- Name: idx_tareas_asignado_por_fecha; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tareas_asignado_por_fecha ON public.tareas USING btree (asignado_por, fecha_inicio);


--
-- TOC entry 5024 (class 1259 OID 17496)
-- Name: idx_tareas_completed_at; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tareas_completed_at ON public.tareas USING btree (completed_at) WHERE (completed_at IS NOT NULL);


--
-- TOC entry 5025 (class 1259 OID 17497)
-- Name: idx_tareas_estado; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tareas_estado ON public.tareas USING btree (id_estado_tarea);


--
-- TOC entry 5026 (class 1259 OID 17498)
-- Name: idx_tareas_prioridad; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tareas_prioridad ON public.tareas USING btree (id_prioridad);


--
-- TOC entry 5012 (class 1259 OID 17487)
-- Name: idx_user_activo; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_activo ON public."USER" USING btree (activo);


--
-- TOC entry 5013 (class 1259 OID 17484)
-- Name: idx_user_agencia; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_agencia ON public."USER" USING btree (id_agencias) WHERE (id_agencias IS NOT NULL);


--
-- TOC entry 5014 (class 1259 OID 17485)
-- Name: idx_user_cargo; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_cargo ON public."USER" USING btree (id_cargo) WHERE (id_cargo IS NOT NULL);


--
-- TOC entry 5015 (class 1259 OID 17486)
-- Name: idx_user_supervisor; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_supervisor ON public."USER" USING btree (id_supervisor) WHERE (id_supervisor IS NOT NULL);


--
-- TOC entry 5048 (class 1259 OID 17540)
-- Name: idx_usuario_cargo_cargo; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_usuario_cargo_cargo ON public.usuario_cargo USING btree (id_cargo);


--
-- TOC entry 5049 (class 1259 OID 17539)
-- Name: idx_usuario_cargo_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_usuario_cargo_user ON public.usuario_cargo USING btree (id_user);


--
-- TOC entry 5029 (class 1259 OID 17500)
-- Name: idx_ventas_fecha; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_ventas_fecha ON public.ventas USING btree (fecha_venta);


--
-- TOC entry 5030 (class 1259 OID 17499)
-- Name: idx_ventas_user_fecha; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_ventas_user_fecha ON public.ventas USING btree (id_user, fecha_venta);


--
-- TOC entry 5053 (class 2606 OID 17359)
-- Name: area fk_area_division; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.area
    ADD CONSTRAINT fk_area_division FOREIGN KEY (id_division) REFERENCES public.division(id_division) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- TOC entry 5054 (class 2606 OID 17389)
-- Name: area fk_area_jf_area; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.area
    ADD CONSTRAINT fk_area_jf_area FOREIGN KEY (id_jf_area) REFERENCES public."USER"(id_user) ON DELETE SET NULL;


--
-- TOC entry 5055 (class 2606 OID 17364)
-- Name: cargo fk_cargo_area; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cargo
    ADD CONSTRAINT fk_cargo_area FOREIGN KEY (id_area) REFERENCES public.area(id_area) ON DELETE CASCADE;


--
-- TOC entry 5056 (class 2606 OID 17369)
-- Name: cargo fk_cargo_division; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cargo
    ADD CONSTRAINT fk_cargo_division FOREIGN KEY (id_division) REFERENCES public.division(id_division) ON DELETE CASCADE;


--
-- TOC entry 5076 (class 2606 OID 17474)
-- Name: division_metrica fk_div_met_division; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.division_metrica
    ADD CONSTRAINT fk_div_met_division FOREIGN KEY (id_division) REFERENCES public.division(id_division) ON DELETE CASCADE;


--
-- TOC entry 5077 (class 2606 OID 17479)
-- Name: division_metrica fk_div_met_metrica; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.division_metrica
    ADD CONSTRAINT fk_div_met_metrica FOREIGN KEY (id_metrica) REFERENCES public.catalogo_metrica(id_metrica) ON DELETE RESTRICT;


--
-- TOC entry 5052 (class 2606 OID 17514)
-- Name: division fk_division_jf_division; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.division
    ADD CONSTRAINT fk_division_jf_division FOREIGN KEY (id_jf_division) REFERENCES public."USER"(id_user) ON DELETE SET NULL;


--
-- TOC entry 5060 (class 2606 OID 17399)
-- Name: historico fk_historico_agencia; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historico
    ADD CONSTRAINT fk_historico_agencia FOREIGN KEY (id_agencias) REFERENCES public.agencias(id_agencias) ON DELETE SET NULL;


--
-- TOC entry 5061 (class 2606 OID 17409)
-- Name: historico fk_historico_area; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historico
    ADD CONSTRAINT fk_historico_area FOREIGN KEY (id_area) REFERENCES public.area(id_area) ON DELETE SET NULL;


--
-- TOC entry 5062 (class 2606 OID 17414)
-- Name: historico fk_historico_cargo; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historico
    ADD CONSTRAINT fk_historico_cargo FOREIGN KEY (id_cargo) REFERENCES public.cargo(id_cargo) ON DELETE SET NULL;


--
-- TOC entry 5063 (class 2606 OID 17404)
-- Name: historico fk_historico_division; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historico
    ADD CONSTRAINT fk_historico_division FOREIGN KEY (id_division) REFERENCES public.division(id_division) ON DELETE SET NULL;


--
-- TOC entry 5064 (class 2606 OID 17419)
-- Name: historico fk_historico_supervisor; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historico
    ADD CONSTRAINT fk_historico_supervisor FOREIGN KEY (id_supervisor) REFERENCES public."USER"(id_user) ON DELETE SET NULL;


--
-- TOC entry 5065 (class 2606 OID 17394)
-- Name: historico fk_historico_user; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historico
    ADD CONSTRAINT fk_historico_user FOREIGN KEY (id_user) REFERENCES public."USER"(id_user) ON DELETE CASCADE;


--
-- TOC entry 5072 (class 2606 OID 17459)
-- Name: objetivo_division_semana fk_obj_div_metrica; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.objetivo_division_semana
    ADD CONSTRAINT fk_obj_div_metrica FOREIGN KEY (id_metrica) REFERENCES public.catalogo_metrica(id_metrica) ON DELETE RESTRICT;


--
-- TOC entry 5073 (class 2606 OID 17454)
-- Name: objetivo_division_semana fk_obj_division; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.objetivo_division_semana
    ADD CONSTRAINT fk_obj_division FOREIGN KEY (id_division) REFERENCES public.division(id_division) ON DELETE CASCADE;


--
-- TOC entry 5074 (class 2606 OID 17464)
-- Name: objetivo_usuario_semana fk_obj_user; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.objetivo_usuario_semana
    ADD CONSTRAINT fk_obj_user FOREIGN KEY (id_user) REFERENCES public."USER"(id_user) ON DELETE CASCADE;


--
-- TOC entry 5075 (class 2606 OID 17469)
-- Name: objetivo_usuario_semana fk_obj_user_metrica; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.objetivo_usuario_semana
    ADD CONSTRAINT fk_obj_user_metrica FOREIGN KEY (id_metrica) REFERENCES public.catalogo_metrica(id_metrica) ON DELETE RESTRICT;


--
-- TOC entry 5066 (class 2606 OID 17434)
-- Name: tareas fk_tareas_area; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tareas
    ADD CONSTRAINT fk_tareas_area FOREIGN KEY (id_area) REFERENCES public.area(id_area) ON DELETE RESTRICT;


--
-- TOC entry 5067 (class 2606 OID 17439)
-- Name: tareas fk_tareas_asignado_a; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tareas
    ADD CONSTRAINT fk_tareas_asignado_a FOREIGN KEY (asignado_a) REFERENCES public."USER"(id_user) ON DELETE RESTRICT;


--
-- TOC entry 5068 (class 2606 OID 17444)
-- Name: tareas fk_tareas_asignado_por; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tareas
    ADD CONSTRAINT fk_tareas_asignado_por FOREIGN KEY (asignado_por) REFERENCES public."USER"(id_user) ON DELETE RESTRICT;


--
-- TOC entry 5069 (class 2606 OID 17429)
-- Name: tareas fk_tareas_estado; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tareas
    ADD CONSTRAINT fk_tareas_estado FOREIGN KEY (id_estado_tarea) REFERENCES public.estado_tarea(id_estado_tarea) ON DELETE RESTRICT;


--
-- TOC entry 5070 (class 2606 OID 17424)
-- Name: tareas fk_tareas_prioridad; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tareas
    ADD CONSTRAINT fk_tareas_prioridad FOREIGN KEY (id_prioridad) REFERENCES public.prioridad(id_prioridad) ON DELETE RESTRICT;


--
-- TOC entry 5057 (class 2606 OID 17374)
-- Name: USER fk_user_agencia; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."USER"
    ADD CONSTRAINT fk_user_agencia FOREIGN KEY (id_agencias) REFERENCES public.agencias(id_agencias) ON DELETE SET NULL;


--
-- TOC entry 5058 (class 2606 OID 17379)
-- Name: USER fk_user_cargo; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."USER"
    ADD CONSTRAINT fk_user_cargo FOREIGN KEY (id_cargo) REFERENCES public.cargo(id_cargo) ON DELETE SET NULL;


--
-- TOC entry 5059 (class 2606 OID 17384)
-- Name: USER fk_user_supervisor; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."USER"
    ADD CONSTRAINT fk_user_supervisor FOREIGN KEY (id_supervisor) REFERENCES public."USER"(id_user) ON DELETE SET NULL;


--
-- TOC entry 5078 (class 2606 OID 17534)
-- Name: usuario_cargo fk_usuario_cargo_cargo; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuario_cargo
    ADD CONSTRAINT fk_usuario_cargo_cargo FOREIGN KEY (id_cargo) REFERENCES public.cargo(id_cargo) ON DELETE RESTRICT;


--
-- TOC entry 5079 (class 2606 OID 17529)
-- Name: usuario_cargo fk_usuario_cargo_user; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuario_cargo
    ADD CONSTRAINT fk_usuario_cargo_user FOREIGN KEY (id_user) REFERENCES public."USER"(id_user) ON DELETE CASCADE;


--
-- TOC entry 5071 (class 2606 OID 17449)
-- Name: ventas fk_ventas_user; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT fk_ventas_user FOREIGN KEY (id_user) REFERENCES public."USER"(id_user) ON DELETE RESTRICT;


-- Completed on 2026-02-06 17:31:42

--
-- PostgreSQL database dump complete
--

\unrestrict tq3nTqFzhOLk0oZ2GMH13WpHsRwH7zSD1Tgbsd2VFKBCS20hErVbsPRU6ojv0hK

