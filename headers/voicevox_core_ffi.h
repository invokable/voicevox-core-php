/* PHP FFI Declaration for VOICEVOX Core v0.16+ (LOAD_ONNXRUNTIME mode)
 * Pre-processed from voicevox_core.h:
 *   - Removed all #ifdef / #if / #define / #endif blocks
 *   - Removed __declspec(dllimport)
 *   - Added missing type definitions (uintptr_t, bool)
 *   - Replaced VoicevoxVoiceModelId typedef with uint8_t*
 *   - Removed C++ typed enum syntax (: int32_t)
 *   - Using VOICEVOX_LOAD_ONNXRUNTIME mode (all non-iOS released binaries)
 */

/* ---- Type preamble ---- */
typedef unsigned char      uint8_t;
typedef unsigned short     uint16_t;
typedef unsigned int       uint32_t;
typedef unsigned long long uint64_t;
typedef signed int         int32_t;
typedef signed long long   int64_t;
typedef uint64_t           uintptr_t;
typedef _Bool              bool;

/* ---- Opaque handles ---- */
typedef struct OpenJtalkRc OpenJtalkRc;
typedef struct VoicevoxOnnxruntime VoicevoxOnnxruntime;
typedef struct VoicevoxSynthesizer VoicevoxSynthesizer;
typedef struct VoicevoxUserDict VoicevoxUserDict;
typedef struct VoicevoxVoiceModelFile VoicevoxVoiceModelFile;

/* ---- Enums (as typedef int32_t) ---- */
typedef int32_t VoicevoxAccelerationMode;
typedef int32_t VoicevoxResultCode;
typedef int32_t VoicevoxUserDictWordType;
typedef uint32_t VoicevoxStyleId;

/* ---- Concrete structs ---- */
typedef struct VoicevoxLoadOnnxruntimeOptions {
    const char *filename;
} VoicevoxLoadOnnxruntimeOptions;

typedef struct VoicevoxInitializeOptions {
    int32_t  acceleration_mode;
    uint16_t cpu_num_threads;
} VoicevoxInitializeOptions;

typedef struct VoicevoxSynthesisOptions {
    bool enable_interrogative_upspeak;
} VoicevoxSynthesisOptions;

typedef struct VoicevoxTtsOptions {
    bool enable_interrogative_upspeak;
} VoicevoxTtsOptions;

typedef struct VoicevoxUserDictWord {
    const char *surface;
    const char *pronunciation;
    uintptr_t   accent_type;
    int32_t     word_type;
    uint32_t    priority;
} VoicevoxUserDictWord;

/* ---- ONNX Runtime (LOAD mode) ---- */
const char *voicevox_get_onnxruntime_lib_versioned_filename(void);
const char *voicevox_get_onnxruntime_lib_unversioned_filename(void);
struct VoicevoxLoadOnnxruntimeOptions voicevox_make_default_load_onnxruntime_options(void);
const struct VoicevoxOnnxruntime *voicevox_onnxruntime_get(void);
int32_t voicevox_onnxruntime_load_once(
    struct VoicevoxLoadOnnxruntimeOptions options,
    const struct VoicevoxOnnxruntime **out_onnxruntime
);

/* ---- OpenJTalk ---- */
int32_t voicevox_open_jtalk_rc_new(
    const char *open_jtalk_dic_dir,
    struct OpenJtalkRc **out_open_jtalk
);
int32_t voicevox_open_jtalk_rc_use_user_dict(
    const struct OpenJtalkRc *open_jtalk,
    const struct VoicevoxUserDict *user_dict
);
int32_t voicevox_open_jtalk_rc_analyze(
    const struct OpenJtalkRc *open_jtalk,
    const char *text,
    char **output_accent_phrases_json
);
void voicevox_open_jtalk_rc_delete(struct OpenJtalkRc *open_jtalk);

/* ---- Core ---- */
struct VoicevoxInitializeOptions voicevox_make_default_initialize_options(void);
const char *voicevox_get_version(void);

/* ---- AudioQuery Utility ---- */
int32_t voicevox_audio_query_create_from_accent_phrases(
    const char *accent_phrases_json,
    char **output_audio_query_json
);
int32_t voicevox_audio_query_validate(const char *audio_query_json);
int32_t voicevox_accent_phrase_validate(const char *accent_phrase_json);
int32_t voicevox_mora_validate(const char *mora_json);
int32_t voicevox_score_validate(const char *score_json);
int32_t voicevox_note_validate(const char *note_json);
int32_t voicevox_frame_audio_query_validate(const char *frame_audio_query_json);
int32_t voicevox_frame_phoneme_validate(const char *frame_phoneme_json);
int32_t voicevox_ensure_compatible(
    const char *score_json,
    const char *frame_audio_query_json
);

/* ---- Voice Model File ---- */
int32_t voicevox_voice_model_file_open(
    const char *path,
    struct VoicevoxVoiceModelFile **out_model
);
void voicevox_voice_model_file_id(
    const struct VoicevoxVoiceModelFile *model,
    uint8_t *output_voice_model_id
);
char *voicevox_voice_model_file_create_metas_json(
    const struct VoicevoxVoiceModelFile *model
);
void voicevox_voice_model_file_delete(struct VoicevoxVoiceModelFile *model);

/* ---- Synthesizer ---- */
int32_t voicevox_synthesizer_new(
    const struct VoicevoxOnnxruntime *onnxruntime,
    const struct OpenJtalkRc *open_jtalk,
    struct VoicevoxInitializeOptions options,
    struct VoicevoxSynthesizer **out_synthesizer
);
void voicevox_synthesizer_delete(struct VoicevoxSynthesizer *synthesizer);
const struct VoicevoxOnnxruntime *voicevox_synthesizer_get_onnxruntime(
    const struct VoicevoxSynthesizer *synthesizer
);
int32_t voicevox_synthesizer_load_voice_model(
    const struct VoicevoxSynthesizer *synthesizer,
    const struct VoicevoxVoiceModelFile *model
);
int32_t voicevox_synthesizer_unload_voice_model(
    const struct VoicevoxSynthesizer *synthesizer,
    const uint8_t *model_id
);
bool voicevox_synthesizer_is_gpu_mode(const struct VoicevoxSynthesizer *synthesizer);
bool voicevox_synthesizer_is_loaded_voice_model(
    const struct VoicevoxSynthesizer *synthesizer,
    const uint8_t *model_id
);
char *voicevox_synthesizer_create_metas_json(
    const struct VoicevoxSynthesizer *synthesizer
);

/* ---- Supported Devices ---- */
int32_t voicevox_onnxruntime_create_supported_devices_json(
    const struct VoicevoxOnnxruntime *onnxruntime,
    char **output_supported_devices_json
);

/* ---- Audio Query ---- */
int32_t voicevox_synthesizer_create_audio_query_from_kana(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *kana,
    uint32_t style_id,
    char **output_audio_query_json
);
int32_t voicevox_synthesizer_create_audio_query(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *text,
    uint32_t style_id,
    char **output_audio_query_json
);
int32_t voicevox_synthesizer_create_accent_phrases_from_kana(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *kana,
    uint32_t style_id,
    char **output_accent_phrases_json
);
int32_t voicevox_synthesizer_create_accent_phrases(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *text,
    uint32_t style_id,
    char **output_accent_phrases_json
);
int32_t voicevox_synthesizer_replace_mora_data(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *accent_phrases_json,
    uint32_t style_id,
    char **output_accent_phrases_json
);
int32_t voicevox_synthesizer_replace_phoneme_length(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *accent_phrases_json,
    uint32_t style_id,
    char **output_accent_phrases_json
);
int32_t voicevox_synthesizer_replace_mora_pitch(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *accent_phrases_json,
    uint32_t style_id,
    char **output_accent_phrases_json
);

/* ---- Synthesis ---- */
struct VoicevoxSynthesisOptions voicevox_make_default_synthesis_options(void);
int32_t voicevox_synthesizer_synthesis(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *audio_query_json,
    uint32_t style_id,
    struct VoicevoxSynthesisOptions options,
    uintptr_t *output_wav_length,
    uint8_t **output_wav
);

struct VoicevoxTtsOptions voicevox_make_default_tts_options(void);
int32_t voicevox_synthesizer_tts(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *text,
    uint32_t style_id,
    struct VoicevoxTtsOptions options,
    uintptr_t *output_wav_length,
    uint8_t **output_wav
);
int32_t voicevox_synthesizer_tts_from_kana(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *kana,
    uint32_t style_id,
    struct VoicevoxTtsOptions options,
    uintptr_t *output_wav_length,
    uint8_t **output_wav
);

/* ---- Singing Voice Synthesis ---- */
int32_t voicevox_synthesizer_create_sing_frame_audio_query(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *score_json,
    uint32_t style_id,
    char **output_frame_audio_query_json
);
int32_t voicevox_synthesizer_create_sing_frame_f0(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *score_json,
    const char *frame_audio_query_json,
    uint32_t style_id,
    char **output_f0_json
);
int32_t voicevox_synthesizer_create_sing_frame_volume(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *score_json,
    const char *frame_audio_query_json,
    uint32_t style_id,
    char **output_volume_json
);
int32_t voicevox_synthesizer_frame_synthesis(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *frame_audio_query_json,
    uint32_t style_id,
    uintptr_t *output_wav_length,
    uint8_t **output_wav
);

/* ---- Memory management ---- */
void voicevox_json_free(char *json);
void voicevox_wav_free(uint8_t *wav);
const char *voicevox_error_result_to_message(int32_t result_code);

/* ---- User Dictionary ---- */
struct VoicevoxUserDictWord voicevox_user_dict_word_make(
    const char *surface,
    const char *pronunciation,
    uintptr_t accent_type
);
struct VoicevoxUserDict *voicevox_user_dict_new(void);
int32_t voicevox_user_dict_load(
    const struct VoicevoxUserDict *user_dict,
    const char *dict_path
);
int32_t voicevox_user_dict_add_word(
    const struct VoicevoxUserDict *user_dict,
    const struct VoicevoxUserDictWord *word,
    uint8_t *output_word_uuid
);
int32_t voicevox_user_dict_update_word(
    const struct VoicevoxUserDict *user_dict,
    const uint8_t *word_uuid,
    const struct VoicevoxUserDictWord *word
);
int32_t voicevox_user_dict_remove_word(
    const struct VoicevoxUserDict *user_dict,
    const uint8_t *word_uuid
);
int32_t voicevox_user_dict_to_json(
    const struct VoicevoxUserDict *user_dict,
    char **output_json
);
int32_t voicevox_user_dict_import(
    const struct VoicevoxUserDict *user_dict,
    const struct VoicevoxUserDict *other_dict
);
int32_t voicevox_user_dict_save(
    const struct VoicevoxUserDict *user_dict,
    const char *path
);
void voicevox_user_dict_delete(struct VoicevoxUserDict *user_dict);
